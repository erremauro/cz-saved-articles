# CZ Saved Articles

**CZ Saved Articles** è un plugin WordPress che permette agli utenti registrati di salvare articoli preferiti tramite un pulsante segnalibro e consultarli in una pagina dedicata.

---

## Funzionalità principali

- Pulsante segnalibro iniettato automaticamente nella toolbar degli articoli (`.czcr-toolbar`).
- Toggle salvataggio/rimozione tramite REST API: un click salva l'articolo, un secondo click lo rimuove.
- Per utenti non registrati il pulsante reindirizza alla pagina di login con redirect back all'articolo corrente.
- Pagina **Articoli Salvati** (`[czsa_saved_articles]`): creata automaticamente all'attivazione del plugin.
  - Lista articoli salvati con autore, titolo e volume di appartenenza (se il plugin `cz-volume` è attivo).
  - Rimozione con conferma inline (senza `window.confirm()`).
  - Messaggio vuoto quando non ci sono articoli salvati.
- Voce **Articoli Salvati** nel menu utente (`czh_nav_user_menu_items`).
- Asset minificati con fallback automatico ai file sorgente se `SCRIPT_DEBUG` è `true`.

---

## Dipendenze

- **cz-volume** (opzionale): se attivo, mostra il nome del volume accanto a ciascun articolo salvato.

---

## Requisiti

- WordPress 6.0+
- PHP 8.0+
- Node.js 18+ (solo per rebuild degli asset)

---

## Installazione

1. Copia la cartella `cz-saved-articles` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. All'attivazione viene creata automaticamente la pagina **Articoli Salvati** (slug `articoli-salvati`) con shortcode `[czsa_saved_articles]`; l'ID viene salvato in `wp_options` come `czsa_saved_page_id`.

---

## Endpoint REST

Namespace: `cz-saved-articles/v1`

| Metodo | Endpoint  | Descrizione |
|--------|-----------|-------------|
| `POST` | `/toggle` | Aggiunge o rimuove un articolo dai salvati per l'utente corrente |

L'endpoint richiede autenticazione (`is_user_logged_in()`).

**Request body:**

```json
{ "post_id": 123 }
```

**Response:**

```json
{ "saved": true, "post_id": 123 }
```

---

## Storage

Gli articoli salvati vengono archiviati come array di ID post nel meta utente `czsa_saved_posts` (via `get_user_meta` / `update_user_meta`).

---

## Build asset

```bash
npm install
npm run build
```

Genera:

- `assets/js/czsa.min.js` + sourcemap
- `assets/css/czsa.min.css` + sourcemap
