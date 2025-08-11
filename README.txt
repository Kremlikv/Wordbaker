*** 
WordBaker is an online language app running at 
https://kremlik.byethost15.com
 
**** 

Features:
- Login (users provide mail so they can be contacted)
- MP3 generation
- Users can create their own quiz.
- Quiz can have pictures (Pixabay) and music (FreePD.com)
- AI creating distractors for a quiz automatically. 
- Language Flashcards with bilingual audio
- Difficult words and Mastered words can be viewed as separate lists.
- User can create new tables or upload some from CSV files
- CVS files must be: no BOM, utf8, first column Czech, second foreign (English, German)- sample file downloadable.
- Ability to share folders with other users.
- User can take a piece of text and transform it into dictionary-like study material.
    - Users can scan PDF, aclean the scan with AI, have it translated and make a bilingual table.
    - Users an paste in text, break it into clean sentences, have it translated and make a bilingual table.
- The project uses only free resouces (AlwaysData, mymemory, byethost, Google Cloud TTS, OpenRouter.ai)
- It is mobile responsive for smalls screens
- The project was designed with the help of AI (ChatGPT5)
- Its versions are backec up at Github.
- I use a yaml script to udate the website from each commit.
- I use a yaml script to make a backup of my database with each commit.


Note:

To function it requires some access keys (to a database, to AI). 
These are stored either in config.php (not shared on github) or in github secrets vault.
But you could make the project work from a different AI  and different database.

