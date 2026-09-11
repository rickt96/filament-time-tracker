/*
 * Filament's link mark (and the mention node, which renders as an <a> once
 * it has a resolved url — see TaskMentionProvider) both ship with
 * openOnClick: false, on purpose: a plain click inside the editor has to
 * place the cursor for editing, not navigate away.
 *
 * This adds the escape hatch every other editor (Notion, Google Docs, VS
 * Code) offers instead: ctrl/cmd+click opens the link in a new tab, leaving
 * a plain click free to keep editing.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The open-link-on-ctrl-click rich editor extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState

    return Extension.create({
        name: 'openLinkOnCtrlClick',

        addProseMirrorPlugins() {
            return [
                new Plugin({
                    key: new PluginKey('openLinkOnCtrlClick'),
                    props: {
                        handleClick(view, pos, event) {
                            if (!(event.ctrlKey || event.metaKey)) {
                                return false
                            }

                            const link = event.target.closest('a[href]')

                            if (!link) {
                                return false
                            }

                            event.preventDefault()
                            window.open(link.href, '_blank', 'noopener,noreferrer')

                            return true
                        },
                    },
                }),
            ]
        },
    })
}
