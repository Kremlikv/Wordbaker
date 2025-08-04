<!DOCTYPE html>
<html>

<head>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WordBaker</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background-color: #f9f9f9;
    }

    header {
      background-color: #666;
      padding: 20px;
      text-align: center;
      font-size: 25px;
      color: white;
    }

    nav ul {
      list-style: none;
      margin: 10px 0 0 0;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px;
    }

    nav li {
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    nav a {
      display: block;
      padding: 10px 20px;
      text-decoration: none;
      font-size: 15px;
      color: #000;
      transition: background-color 0.3s ease;
    }

    a {
      text-decoration: none;
      color: #000000;
      transition: 0.3s;
    }

    .content {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
      text-align: center;
    }

    .intro {
      background-color: #f1f1f1;
      text-align: center;
      padding: 20px;
    }

    .row {
      display: flex;
      flex-wrap: wrap;
    }

    .column1,
    .column2
    .column3
    .column4 {
      width: 50%;
      padding: 20px;
    }

    .column1 {
      background-color: #f1f1f1;
    }

    .column2 {
      background-color: #666;
      color: white;
    }

    .column3 {
      background-color: #FFFAF0;
     
    }

    .column4 {
      background-color: #000000;
      color: white;
    }
    
    table {
      margin: 0 auto;
      border-collapse: collapse;
      background-color: white;
    }

    table th,
    table td {
      padding: 10px;
      text-align: center;
      border: 1px solid #ccc;
    }

    footer {
      background-color: #6661;
      padding: 10px;
      text-align: center;
      // color: white;
      clear: both;
      position: relative;
      bottom: 0;
      width: 100%;
    }

    /* ✅ MENU BAR under header */
    .menu-bar {
      background-color: #eee;
      display: flex;
      justify-content: center;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid #ccc;
      flex-wrap: wrap;
    }

    .menu-link {
      background-color: #ddd;
      color: black;
      text-decoration: none;
      padding: 10px 16px;
      font-size: 1em;
      border-radius: 6px;
      display: inline-block;
      transition: background-color 0.2s;
    }

    .menu-link:hover {
      background-color: #bbb;
    }

    .selected-table {
      font-weight: bold;
      color: #006699;
    }

    .delete-button {
      background-color: #ff4444;
      color: white;
      padding: 10px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }

    .delete-button:hover {
      background-color: #cc0000;
    }

    audio {
      margin-bottom: 10px;
    }

    /* ✅ MOBILE RESPONSIVE ADJUSTMENTS */
    @media (max-width: 768px) {
      nav ul {
        flex-direction: column;
        gap: 5px;
      }

      .row {
        flex-direction: column;
      }

      .column1,
      .column2 {
        width: 100%;
        padding: 10px;
      }

      header {
        font-size: 20px;
        padding: 15px;
      }

      nav a {
        font-size: 14px;
        padding: 8px 16px;
      }

      .content {
        padding: 10px;
      }

      table th,
      table td {
        padding: 6px;
      }

      .menu-bar {
        flex-direction: column;
        gap: 6px;
      }
    }

    /* buttons */
    button {
      font-size: 0.8em;
      padding: 15px 20px;
      border-radius: 6px;
      cursor: pointer;
    }

    @media (max-width: 600px) {
      button {
        font-size: 1em;
        padding: 10px 15px;
      }
    }


    a button {
      margin: 5px;
      padding: 10px 15px;
      font-size: 16px;
      border-radius: 8px;
      border: 1px solid #888;
      background-color: #f0f0f0;
      cursor: pointer;
      transition: background 0.3s;
    }

    a button:hover {
      background-color: #ddd;
    }

    /* Responsive editable screens.  */

    textarea {
      width: 100%;
      min-height: 1.5em;
      resize: none;               /* Disable manual resize handles */
      overflow: hidden;           /* Prevent scrollbars */
      box-sizing: border-box;
      font-family: inherit;
      font-size: 1em;
    }

    @media screen and (max-width: 600px) {
          
      table {
          font-size: 0.9em;
      }

      th, td {
          padding: 6px;
      }

      .delete-button {
          font-size: 0.9em;
      }
    }

/* Explorer-like table selection with folders */ 

<style>
.tree-view ul {
    list-style-type: none;
    padding-left: 20px;
}

.tree-view li {
    margin: 5px 0;
    cursor: pointer;
}

.folder-toggle::before {
    content: '▶ ';
    display: inline-block;
    margin-right: 5px;
}

.folder-toggle.open::before {
    content: '▼ ';
}

ul ul {
    display: none;
}

ul ul.open {
    display: block;
}

.table-leaf:hover {
    background-color: #eef;
}

/* Directory panel frame */ 

.directory-panel {
    max-width: 100%;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    background-color: #fdfdfd;
}
@media (max-width: 600px) {
    .directory-panel {
        max-height: 200px;
        padding: 8px;
    }
}


</style>

  
  </head>


</head>

<body>

  <header>
    <h1>WordBaker</h1>
    <nav>
      <ul>
        <li><a href="register.php">🔒Register</a></li>
        <li><a href="login.php">🔑Login</a></li>
      </ul>
    </nav>
  </header>

  
<div class="content">
    <!-- your page content here -->  
 



  <section class="intro">
    <h1>Let's bake some words!</h1>

  </section>

  <div class="row">

    <div class="column2">
      <h1>Flip the pancake</h1>
            <p><b>Addictive learning system</b><br>
            <i> <br>            
            Flashcards show you a question (Czech word) and when you flip the card, you can see the answer (e.g English word),<br>
            <br>
            Audio for both sides of the card is available.<br>
            <br>
            You can mark the words that are difficult and focus solely on them.<br>
            <br>
            Once you have mastered a word, you can delete it from the list of difficult words.<br>
            <br>
            Weeks later you can review the list of mastered words to see if you have not forgotten them again.<br>                      
            </i><br>    
                       

    </div>

     <div class="column3">
      <h1>Modern cuisine</h1>
      <p><b>Designed with AI</b><br>    
          <i> <br>
          Automatic translation by MyMemory, the world's largest translation memory (by Translated Labs).<br>
          <br>
          AI voice generation by Google Cloud TTS (default) or Elevenlabs (more voice to choose from).<br>
          <br>  
          PDF analysis (parsing) with OCR (Optical Character Recognition) allows extracting text from PDF files.<br>
          <br>
          AI allows for cleaning of garbled text and correcting errors.<br>
          </i>.<br>
        </p>
    </div> 

      <div class="column4">
      <h1>Cook your own</h1>
      <p><b>Create your own study material</b><br>   
           <i> <br>
           You can scan text from PDF. Either whole pages are loaded in or you select a part of the page visually - with a mouse.<br>
           <br>
           You can translate the text|(PDF scanned or written/pasted by you) and turn it into a bilingual table with two columns.<br>
           <br>
           You can generate an mp3 audio for your words (and choose the voices) for listening in your car.<br>
           </i>.<br>
        </p>
    </div> 

  </div> <!-- >end of row -->

  <footer>
    <p>(c) Vítězslav Kremlík 2025 (kremlik@seznam.cz)</p>
  </footer>

</div> <!-- div class content -->
</body>
</html>
