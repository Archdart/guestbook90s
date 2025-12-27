# Guestbook90s

> Barebone "90s internet" style guestbook script in **PHP + JS + JSON**  
> No database required, no frameworks, pure old-school vibes.

[Demo](https://polybius.ovh/guestbook/) 

---

## Descrizione

Guestbook90s è un piccolo script per aggiungere un guestbook stile anni ’90 al tuo sito web.  
Tutti i messaggi vengono salvati in un **file JSON**, nessun database richiesto, tutto leggero e facile da integrare in **Bludit** o in qualsiasi sito PHP statico.  

Perfetto per chi ama il **nostalgic web design**, hacker-ish look, o semplicemente vuole un guestbook veloce e indipendente.

---

## Features

- Salvataggio messaggi in JSON, niente MySQL o altri DB
- Form minimal “90s style” integrato
- Paginazione lato client: ultimi 10 messaggi in cima
- Pulsanti **Vedi messaggi precedenti** / **Messaggi più recenti**
- Filtro honeypot per ridurre spam automatico
- Visualizzazione chiara: `[Data] Nome ha scritto: Messaggio`
- Tutto inline: PHP + JS + JSON
- Completamente **portable**, nessuna installazione complessa

---

## Installazione

1. Copia la cartella `guestbook` nella directory del tuo tema Bludit o nel tuo sito PHP
2. Assicurati che il server PHP abbia i permessi di scrittura su `entries.json`  
   (es. `chmod 666 entries.json` o equivalente)
3. Includi il file PHP nel tuo template o nella pagina desiderata:

```php
<?php include('guestbook/guestbook.php'); ?>
