V README uvádím poznámky k průběhu projektu a řešení nastalých problémů.

**** ****

MOBILE RESPONSIVE DESIGN
Suitable for mobile phones as well as PC.

TRANSLATOR 
It translates your text and creates a table 
with Czech column and foreign language column.

PDF OCR
It scans a PDF, cleans any garbled text with AI and sends it for translation.

OPRAVY
- Php soubor musí začínat <?php hned na prvním řádku bez mezery.
- Include session musí být hned na začátku souboru.
- Spojení s databází je lépe ošetřit pomocí odkazu na soubor db.php 
a ne jako několika řádkový skript, co bych musel v každém souboru opakovat.
- Při github zálohování se mi v byethost adresáři zjevuje dávno smazaný Flashcards.php
A program se rozhodl používat tento neplatn soubor místo nového flashcards.php

ALWAYS DATA MÁ DÁLKOVÉ SQL
Free hosting jako byethost obvykle neumožňuje dálkový přístup k databázím. 
Ale free databázové služby to umožňují, takže stačí propojit tyto dvě služby na dálku.
Na free databázový server mohu soubory nahrávat jen přes FileZillu (win program)
Ve free databázové službě AlwaysData pak databázi prohlížím v phpadmin.

STEJNÁ BRANCH 
db_backup.yaml umožňuje na dálku řídit databázi v AlwayData.
db_backup.yaml funguje až když jsem nastavil push: branch01
(stejná branch jako u php files).
skript to pak ale stejně uloží ten sql dump do branche: db_backups.

TOKEN
db_backup.yaml také vyžaduje, abych v nastavení projektu v githubu
šel na: Settings → Actions → General... Scroll to Workflow permissions.
Make sure this is selected: 🔘 Read and write permissions

SECRETS
Hesla v Githubu uložit v samostatném šuplíku,
jinak neumožní zveřejnit adresář jako "veřejný".

GITHUB ACTIONS 
Na Githubu v sekci Actions vidím, zda se yaml skript zálohování obou věcí
(databáze AlwaysData, php adresář na Byethost) podařilo... 

EDITABLE TABLES  
After revert we are back to editable tables where you can add a row.
Or delete a row. And we still use ElevenLabs.

DIRECTORIES AND FOLDERS
On the main.php with rules like kremlik_prag_pulverturm.

FREE AUDIO FROM GOOGLE TTS
Required a full overhaul of the files. 31/7/25

FREE
Graduaally I switched to free sources. 
a) Free AlwaysData hosting (allowign scripted backups in git).
b) Free translation (mymemory). 
c) Free audio (google tts). 
d) And also I replaced AI with a free version from OpenRouter.ai 


