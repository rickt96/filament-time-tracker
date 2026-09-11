<?php

namespace App\Services;

use App\Filament\Resources\Documents\Schemas\DocumentForm;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\CommonMark\CommonMarkConverter;
use PhpOffice\PhpWord\IOFactory;
use ZipArchive;

/**
 * Converte un file caricato (txt/md/html/docx/odt) in [title, content HTML]
 * pronti per un Document — content è sempre HTML perché è quello che l'editor
 * (AdvancedRichEditor) si aspetta, vedi DocumentForm::getContentEditor().
 *
 * Immagini: txt/md/html le portano già dentro (link o base64) e restano così.
 * docx/odt vanno gestite: le immagini incorporate vengono estratte e salvate
 * sul disco allegati dell'editor, e nell'HTML resta un <img> che punta all'URL.
 */
class DocumentImportService
{
    private const SUPPORTED_EXTENSIONS = ['txt', 'md', 'html', 'docx', 'odt'];

    private const ODF_NS_DRAW = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';

    private const ODF_NS_XLINK = 'http://www.w3.org/1999/xlink';

    /**
     * @return array{title: string, content: string}
     */
    public function parse(string $absolutePath, string $originalFilename): array
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("Formato non supportato: .{$extension}");
        }

        $title = pathinfo($originalFilename, PATHINFO_FILENAME);

        $content = match ($extension) {
            'md' => $this->parseMarkdown($absolutePath),
            'txt' => $this->parseText($absolutePath),
            'html' => $this->extractBody(file_get_contents($absolutePath)),
            'docx' => $this->externalizeInlineImages($this->parseOffice($absolutePath, 'Word2007')),
            'odt' => $this->parseOdt($absolutePath),
        };

        return ['title' => $title, 'content' => $content];
    }

    private function parseMarkdown(string $path): string
    {
        return (string) (new CommonMarkConverter)->convert(file_get_contents($path));
    }

    private function parseText(string $path): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim(file_get_contents($path)));

        return collect($lines)
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
            ->implode('');
    }

    /**
     * docx/odt passano entrambi da PhpWord: si carica col reader giusto e si
     * riscrive come HTML, poi si tiene solo il body — il writer produce un
     * documento completo (html/head/style compresi) che l'editor non si aspetta.
     */
    private function parseOffice(string $path, string $readerType): string
    {
        $phpWord = IOFactory::load($path, $readerType);

        $tempHtmlPath = tempnam(sys_get_temp_dir(), 'docimport').'.html';

        try {
            IOFactory::createWriter($phpWord, 'HTML')->save($tempHtmlPath);
            $html = file_get_contents($tempHtmlPath);
        } finally {
            @unlink($tempHtmlPath);
        }

        return $this->extractBody($html);
    }

    /**
     * Il writer HTML di PhpWord (docx) incorpora già le immagini come data URI
     * base64: le si tira fuori e si salvano come file, tenendo un <img> che
     * punta all'URL — così non gonfiano la colonna content e l'editor le
     * tratta come un qualsiasi allegato.
     */
    private function externalizeInlineImages(string $html): string
    {
        return preg_replace_callback(
            '/src="data:image\/([a-z0-9.+-]+);base64,([^"]+)"/i',
            function (array $matches): string {
                $binary = base64_decode($matches[2], true);

                if ($binary === false || $binary === '') {
                    return $matches[0];
                }

                return 'src="'.$this->storeImage($binary, $matches[1]).'"';
            },
            $html,
        );
    }

    /**
     * PhpWord non legge le immagini dagli ODT: stanno in Pictures/ dentro lo
     * zip, referenziate in content.xml da <draw:image xlink:href>. Le si estrae,
     * si salvano, e nel content.xml ogni frame-immagine viene sostituito con un
     * token testuale; poi PhpWord fa la conversione HTML e i token vengono
     * rimpiazzati con gli <img> definitivi, mantenendo la posizione nel flusso.
     */
    private function parseOdt(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return $this->parseOffice($path, 'ODText');
        }

        $contentXml = $zip->getFromName('content.xml');

        if ($contentXml === false) {
            $zip->close();

            return $this->parseOffice($path, 'ODText');
        }

        $dom = new DOMDocument;

        if (! @$dom->loadXML($contentXml)) {
            $zip->close();

            return $this->parseOffice($path, 'ODText');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('draw', self::ODF_NS_DRAW);

        $tokens = [];
        $index = 0;

        /** @var DOMElement $image */
        foreach (iterator_to_array($xpath->query('//draw:image')) as $image) {
            $href = $image->getAttributeNS(self::ODF_NS_XLINK, 'href');
            $binary = $href !== '' ? $zip->getFromName(ltrim($href, '/')) : false;

            if ($binary === false || $binary === '') {
                continue;
            }

            $token = "@@DOC_IMG_{$index}@@";
            $tokens[$token] = '<img src="'.$this->storeImage($binary, pathinfo($href, PATHINFO_EXTENSION)).'" alt="">';
            $index++;

            // Sostituisce l'intero frame (o l'immagine, se non è dentro un frame)
            // con un semplice nodo di testo che porta il token.
            $target = $this->closestDrawFrame($image) ?? $image;
            $target->parentNode?->replaceChild($dom->createTextNode($token), $target);
        }

        $zip->close();

        if ($tokens === []) {
            return $this->parseOffice($path, 'ODText');
        }

        $tempOdtPath = tempnam(sys_get_temp_dir(), 'docimport').'.odt';
        copy($path, $tempOdtPath);

        try {
            $tempZip = new ZipArchive;
            $tempZip->open($tempOdtPath);
            $tempZip->addFromString('content.xml', $dom->saveXML());
            $tempZip->close();

            $html = $this->parseOffice($tempOdtPath, 'ODText');
        } finally {
            @unlink($tempOdtPath);
        }

        return strtr($html, $tokens);
    }

    private function closestDrawFrame(DOMElement $image): ?DOMElement
    {
        $node = $image->parentNode;

        while ($node instanceof DOMElement) {
            if ($node->namespaceURI === self::ODF_NS_DRAW && $node->localName === 'frame') {
                return $node;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * Salva un'immagine sul disco allegati dell'editor, nella stessa cartella
     * usata dall'AdvancedRichEditor, e ne restituisce l'URL pubblico.
     */
    private function storeImage(string $binary, string $extension): string
    {
        $extension = strtolower($extension);
        $extension = match ($extension) {
            'jpeg' => 'jpg',
            'svg+xml' => 'svg',
            default => preg_match('/^[a-z0-9]+$/', $extension) ? $extension : 'bin',
        };

        $path = DocumentForm::CONTENT_ATTACHMENTS_DIRECTORY.'/'.Str::uuid()->toString().'.'.$extension;

        $disk = Storage::disk($this->attachmentsDiskName());
        $disk->put($path, $binary);

        return $disk->url($path);
    }

    /**
     * Stesso criterio di Filament: con disco di default `local` gli allegati
     * dell'editor finiscono su `public` (unico URL-accessibile).
     */
    private function attachmentsDiskName(): string
    {
        $disk = config('filament.default_filesystem_disk');

        return $disk === 'local' ? 'public' : $disk;
    }

    /**
     * Un .html caricato può essere un documento completo (html/head/body) o
     * già solo un frammento: se c'è un <body> se ne tiene solo il contenuto,
     * altrimenti l'HTML viene usato così com'è. Il caricamento nell'editor
     * (Tiptap) fa comunque da sanitizzazione, accettando solo lo schema di
     * nodi/marks che conosce — vedi la nota di sicurezza in MarkdownEditor.
     */
    private function extractBody(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return trim($html);
    }
}
