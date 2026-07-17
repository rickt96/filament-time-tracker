Obiettivo:
Realizzare in Laravel 12 + Filament 4 un'applicazione di time tracking ispirata alle funzionalità essenziali di Clockify Free, mantenendo un'architettura pulita, estendibile e modulare.

Stack:
- Laravel
- Filament
- Livewire
- PHP 8.3+
- MySQL/SQLite
- Autenticazione Laravel
- Policies per autorizzazioni
- Soft Deletes ove opportuno

Principi progettuali:
- Ogni entità deve avere CRUD completo tramite Filament.
- Utilizzare Relation Manager quando appropriato.
- Tabelle con ricerca, filtri, ordinamento e colonne configurabili.
- Form validati.
- Codice conforme alle best practice Laravel.
- Struttura facilmente estendibile.
- Nessuna logica duplicata.

---

Entità principali

Workspace
- nome
- descrizione
- proprietario
- impostazioni generali

Utente
- dati anagrafici
- email
- ruolo
- appartenenza a uno o più workspace

Cliente
- nome
- descrizione
- contatti
- colore identificativo
- stato attivo

Progetto
- nome
- cliente associato
- descrizione
- colore
- stato attivo/archiviato
- visibilità
- membri assegnati

Tag
- nome
- colore
- descrizione

Attività (Task opzionale)
- nome
- progetto
- descrizione
- stato
- assegnatario

Time Entry
- utente
- progetto
- cliente (derivato dal progetto)
- task opzionale
- descrizione
- data
- ora inizio
- ora fine
- durata calcolata
- timer attivo
- tag multipli
- modificabile
- eliminabile

---

Funzionalità Timer

Implementare un timer simile a Clockify:

- pulsante Start
- pulsante Stop
- possibilità di riprendere un timer
- timer unico attivo per utente
- aggiornamento live della durata
- compilazione manuale possibile
- modifica dopo il salvataggio
- timer persistente anche dopo refresh browser

---

Inserimento manuale

Permettere:

- inserimento durata
- inserimento intervallo orario
- inserimento solo minuti
- modifica successiva

---

Dashboard

Visualizzare:

- ore lavorate oggi
- questa settimana
- questo mese
- ultimi time entry
- progetto più utilizzato
- cliente più utilizzato
- timer attualmente in esecuzione
- grafici riepilogativi

---

Calendario

Vista giornaliera

Vista settimanale

Vista mensile

Visualizzazione delle ore registrate

Possibilità di aprire/modificare un time entry direttamente dal calendario.

---

Report

Filtri:

- intervallo date
- cliente
- progetto
- tag
- utente
- task

Visualizzare:

- totale ore
- totale per progetto
- totale per cliente
- totale per utente
- totale per giorno
- totale per settimana
- totale per mese

Esportazione:

- CSV
- Excel
- PDF (opzionale)

---

Ricerca

Ricerca globale su:

- progetto
- cliente
- descrizione
- tag
- task

---

Preferiti

Consentire di marcare:

- progetti preferiti
- clienti preferiti

per velocizzare la selezione durante la registrazione delle ore.

---

Duplicazione

Consentire:

- duplicare un time entry
- copiare il giorno precedente
- copiare l'ultima attività svolta

---

Progetti

Per ogni progetto gestire:

- cliente
- membri
- stato
- colore
- descrizione
- ore totali registrate
- data creazione

---

Clienti

Per ogni cliente gestire:

- elenco progetti
- totale ore
- numero progetti
- stato

---

Tag

Gestione CRUD completa.

Possibilità di associare più tag ad ogni time entry.

---

Statistiche

Visualizzare:

- ore per progetto
- ore per cliente
- ore per giorno
- ore per settimana
- ore per mese
- media giornaliera
- media settimanale

---

Filament Resources

Creare Resource dedicate per:

- Workspace
- Users
- Clients
- Projects
- Tasks
- Tags
- Time Entries

Ogni Resource deve includere:

- Form
- Table
- Filters
- Global Search
- Relation Managers
- Actions
- Bulk Actions

---

Widget Filament

Creare widget per:

- Timer attivo
- Ore oggi
- Ore settimana
- Ore mese
- Ultimi inserimenti
- Grafico ore per progetto
- Grafico ore per cliente

---

Business Rules

- Un solo timer attivo per utente.
- L'ora di fine deve essere successiva all'ora di inizio.
- La durata viene sempre calcolata automaticamente.
- Non sono ammessi intervalli sovrapposti per lo stesso utente.
- I progetti archiviati non possono ricevere nuove registrazioni.
- I clienti inattivi non possono essere selezionati.
- Eliminazione logica dove appropriato.

---

Esperienza Utente

- Interfaccia molto veloce.
- Apertura rapida del timer.
- Selezione progetto tramite ricerca.
- Selezione cliente automatica dal progetto.
- Tag selezionabili rapidamente.
- Salvataggio con pochi click.
- Responsive.
- Supporto tema chiaro/scuro.

---

Architettura

- Service classes per la logica del timer.
- Repository solo se necessari.
- Policies per autorizzazioni.
- Eventi per start/stop timer.
- Test Feature e Unit.
- Enum PHP per stati.
- Action dedicate per le operazioni complesse.
- Calcoli centralizzati.
- Nessuna logica nei controller o nelle Resource oltre la presentazione.

L'obiettivo non è clonare integralmente Clockify, ma implementarne le funzionalità fondamentali di gestione del tempo, con una struttura moderna, mantenibile ed estendibile che permetta di aggiungere facilmente funzionalità future come fatturazione, tariffe orarie, approvazione timesheet, budget di progetto e integrazioni esterne.

---

Integrazione con sistemi esterni

L'applicazione deve essere progettata per sincronizzare manualmente le ore registrate verso sistemi di project management esterni (es. ClickUp, Jira), utilizzando un'architettura a driver facilmente estendibile.

L'obiettivo è permettere l'aggiunta di nuovi provider senza modificare la logica applicativa.

Modello Cliente

Ogni Cliente deve prevedere:

- sync_driver (string o enum)
- sync_configuration (json)

sync_driver identifica il provider da utilizzare.

Esempi:

- clickup
- jira
- nessun driver

sync_configuration contiene tutte le impostazioni specifiche del provider, ad esempio:

- endpoint
- workspace id
- project id
- token
- username
- email
- api key

La struttura del JSON dipende esclusivamente dal driver selezionato.

---

Modello Task

Ogni Task deve prevedere:

- external_id (string nullable)

Questo campo rappresenta l'identificativo del task nel sistema esterno.

Non è responsabilità dell'app creare i task remoti.

L'associazione tra task locale e task remoto viene effettuata valorizzando external_id.

---

Sincronizzazione

La sincronizzazione NON deve essere automatica.

Deve essere avviata esclusivamente tramite Action di Filament.

Esempi:

- Sync singolo Time Entry
- Sync multipla tramite Bulk Action
- Sync di tutti i Time Entry filtrati

Ogni sincronizzazione deve:

- recuperare il driver configurato sul Cliente
- istanziare dinamicamente il driver corretto
- convertire il Time Entry nel formato richiesto dal provider
- inviare le ore al sistema esterno
- salvare il risultato della sincronizzazione

Ogni Time Entry deve poter essere sincronizzato più volte.

---

Stato sincronizzazione

Ogni Time Entry deve memorizzare:

- synced_at
- sync_status
- sync_error (nullable)

Possibili stati:

- pending
- synced
- failed

In caso di errore il messaggio deve essere salvato per consentire un nuovo tentativo.

---

Architettura Driver

Realizzare un sistema basato su Strategy Pattern.

Prevedere:

SyncDriverInterface

Metodi indicativi:

- validateConfiguration()
- syncTimeEntry()
- testConnection()

Ogni provider implementa la propria classe.

Esempi:

ClickUpDriver

JiraDriver

In futuro sarà possibile aggiungere nuovi driver senza modificare il codice esistente.

Una Factory o Manager deve risolvere automaticamente il driver corretto in base al valore configurato sul Cliente.

---

Budget progetto

Ogni Progetto deve prevedere:

- budget_hours
- hourly_rate

budget_hours rappresenta il monte ore acquistato dal cliente.

hourly_rate rappresenta la tariffa oraria predefinita del progetto.

---

Time Entry

Ogni Time Entry deve salvare una copia della tariffa oraria utilizzata al momento della registrazione.

Campi:

- hourly_rate
- total_amount (calcolato)

La tariffa del Time Entry viene inizialmente valorizzata con quella del Progetto, ma può essere modificata manualmente senza alterare gli altri inserimenti.

Questo garantisce la storicizzazione dei costi.

---

Calcoli economici

Per ogni Progetto calcolare automaticamente:

- ore registrate
- ore residue
- percentuale budget consumato
- costo totale
- ricavo totale
- budget economico residuo

Formula:

ore registrate = somma delle durate dei Time Entry

costo totale = somma(TimeEntry.duration × TimeEntry.hourly_rate)

ore residue = budget_hours - ore registrate

budget consumato = ore registrate / budget_hours

---

Dashboard Progetto

Visualizzare:

- Budget ore
- Ore consumate
- Ore residue
- Percentuale utilizzo
- Ricavo totale
- Tariffa oraria progetto
- Ultime attività

Aggiungere indicatori visivi quando il budget supera soglie configurabili (ad esempio 80%, 90%, 100%).

---

Report economici

Estendere i report esistenti con:

- costo per progetto
- costo per cliente
- costo per periodo
- ricavo per progetto
- ricavo per cliente
- ricavo per utente
- tariffa media applicata
- confronto tra budget previsto e budget consumato

---

Business Rules aggiuntive

- La modifica della tariffa del Progetto non modifica i Time Entry già registrati.
- La tariffa viene copiata sul Time Entry al momento della creazione.
- Il costo del Time Entry viene sempre ricalcolato utilizzando la tariffa salvata sul Time Entry.
- Un Task può essere sincronizzato solo se possiede un external_id.
- Un Cliente senza sync_driver non consente la sincronizzazione.
- La configurazione JSON deve essere validata dal driver selezionato.
- Tutta la logica di sincronizzazione deve essere incapsulata nei driver, senza condizioni specifiche sparse nel codice.

---

## Work Package (Budget Package)

Un Progetto può essere suddiviso in uno o più Work Package, che rappresentano i pacchetti di lavoro previsti dal contratto (es. "Implementazione servizi", "Sviluppo form dinamici", "Testing", "Bug fixing").

I Work Package sono indipendenti dai Task e rappresentano esclusivamente un'unità di gestione del budget e della rendicontazione.

---

### Modello Work Package

Ogni Work Package deve prevedere almeno i seguenti campi:

- project_id
- name
- description
- budget_hours
- hourly_rate (nullable)
- sort_order
- status

Il campo `hourly_rate` è opzionale e, se valorizzato, può essere utilizzato come tariffa di default per i nuovi Time Entry dei Task appartenenti al Work Package. In caso contrario viene utilizzata la tariffa del Progetto.

---

### Relazioni

```text
Client
    └── Project
            ├── Work Package
            │       └── Tasks
            │               └── Time Entries
            │
            └── Budget globale
```

Le relazioni sono:

- Project hasMany WorkPackages
- WorkPackage belongsTo Project
- WorkPackage hasMany Tasks
- Task belongsTo WorkPackage
- Task hasMany TimeEntries

I Time Entry non appartengono direttamente al Work Package, ma vi appartengono indirettamente tramite il Task.

---

### Budget

Ogni Work Package possiede un proprio monte ore indipendente.

Per ogni Work Package devono essere calcolati automaticamente:

- ore registrate
- ore residue
- percentuale budget consumato
- costo totale
- ricavo totale

Le ore registrate sono ottenute dalla somma dei Time Entry di tutti i Task appartenenti al Work Package.

---

### Dashboard

Per ogni Work Package visualizzare:

- Budget ore
- Ore consumate
- Ore residue
- Percentuale di utilizzo
- Tariffa oraria
- Numero Task
- Ultime attività

Prevedere indicatori visivi configurabili per il superamento di soglie di consumo del budget (ad esempio 80%, 90% e 100%).

---

### Report

Estendere i report con aggregazioni per Work Package.

Visualizzare:

- ore per Work Package
- costo per Work Package
- ricavo per Work Package
- budget residuo
- percentuale di utilizzo
- confronto tra budget previsto e budget consumato

I report devono poter essere filtrati anche per Work Package.

---

### Business Rules

- Ogni Task deve appartenere ad un solo Work Package.
- Un Work Package appartiene ad un solo Progetto.
- Le ore di un Time Entry contribuiscono sia al budget del Work Package sia al budget complessivo del Progetto.
- Lo spostamento di un Task da un Work Package ad un altro ricalcola automaticamente tutte le statistiche.
- La modifica del budget di un Work Package non altera i Time Entry esistenti.
- Un Work Package può esistere anche senza Task associati.
- Non utilizzare Task padre/Subtask per rappresentare il budget: i Task rappresentano il lavoro operativo, mentre i Work Package rappresentano esclusivamente la suddivisione contrattuale e contabile del progetto.

---

### Motivazione architetturale

I Work Package sono una struttura interna all'applicazione e non devono essere sincronizzati con sistemi esterni come Jira o ClickUp.

Le integrazioni continuano a basarsi esclusivamente sui Task (tramite `external_id`), mantenendo separati i concetti di:

- Progetto: contratto complessivo con il cliente.
- Work Package: suddivisione del budget e dei costi.
- Task: unità operative sincronizzabili con sistemi esterni.
- Time Entry: consuntivazione delle ore lavorate.

Questa separazione mantiene il dominio coerente, evita di sovraccaricare il concetto di Task con responsabilità contabili e rende il sistema facilmente estendibile a future funzionalità come centri di costo, milestone, SLA, preventivi e analisi di marginalità.