
<!DOCTYPE html>
<html>

<head>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WordBaker</title>
  <style>

</style>

 
</head>


<body>

  <?php include 'styling_welcome.php'; ?>

  
<div class="content">
    <!-- your page content here -->  
 



  <section class="intro">
  
    <img src="ItalianChef.png" alt="ItalianChef" style="max-width: 60%; height: auto">

  </section>

  <div class="row">

    <div class="content">
      

<section class="tiles">
  <details class="tile">
    <summary>Překlady</summary>
    <div class="tile-body">
      <p>Překlady a korektury z/do anglického jazyka. </p>
    </div>
  </details>

  <details class="tile">
    <summary>Výuka</summary>
    <div class="tile-body">
      <form class="enquiry-form" method="post" action="submit_interest.php">
        <div class="row">
          <div>
            <label for="name">Jméno</label>
            <input type="text" id="name" name="name" placeholder="Vaše jméno" required>
          </div>
          <div>
            <label for="contact">Kontakt (email či tel)</label>
            <input type="text" id="contact" name="contact" placeholder="např. jana@example.com nebo +420…" required>
          </div>
        </div>



        <div class="row">
            <div>
              <label for="level">Úroveň</label>
              <select id="level" name="level" required>
                <option value="" selected disabled>Vyberte úroveň</option>
                <option>A1</option><option>A2</option>
                <option>B1</option><option>B2</option>
                <option>C1</option><option>C2</option>
              </select>
            </div>

            <div>
              <label>Časové možnosti</label>
              <div class="days">
                <div class="day-row">
                  <input type="checkbox" id="mon" name="days[]" value="Mon">
                  <label for="mon">Po</label>
                  <input type="text" name="times[Mon]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="tue" name="days[]" value="Tue">
                  <label for="tue">Út</label>
                  <input type="text" name="times[Tue]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="wed" name="days[]" value="Wed">
                  <label for="wed">St</label>
                  <input type="text" name="times[Wed]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="thu" name="days[]" value="Thu">
                  <label for="thu">Čt</label>
                  <input type="text" name="times[Thu]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="fri" name="days[]" value="Fri">
                  <label for="fri">Pa</label>
                  <input type="text" name="times[Fri]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="sat" name="days[]" value="Sat">
                  <label for="sat">So</label>
                  <input type="text" name="times[Sat]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="sun" name="days[]" value="Sun">
                  <label for="sun">Ne</label>
                  <input type="text" name="times[Sun]" placeholder="např. 8:00–10:00, 18:00–20:00">
                </div>
              </div>
            </div>
          </div>

          <div>
            <label for="start_date">Zahájení</label>
            <input type="date" id="start_date" name="start_date">
          </div>


        <div>
          <label for="notes">Poznámky</label>
          <textarea id="notes" name="notes" placeholder="Cíle, témata, online či osobně, preference..."></textarea>
        </div>

        <div>
          <button class="enquiry-submit" type="submit">Odeslat</button>
        </div>
      </form>
    </div>
  </details>

  <details class="tile">
    <summary>Appka</summary>
    <div class="tile-body">
      <p>Využijte online aplikaci Wordbaker. 
        Převeďte text z PDF, nechte si ho přeložit.<br><br>
        Vytvořte si dvojjazyčný slovníček. <bn><br>
        Udělejte si z něj MP3.<br><br>
        Procvičujte se pomocí kartiček.<br><br>
        Udržuje si přehled, co jste se který měsíc naučili.<br><br> 
      </p>
    </div>
  </details>

  <details class="tile">
    <summary>Průvodce</summary>
    <div class="tile-body">
      <p>Chcete-li se projít po Praze a něco se o ní dozvědět, napište.<br><br>
         Mám licenci Český národní průvodce II stupně a oprávnění Průvodce Prahou.<br><br>
      </p>

    </div>
  </details>
</section>



    </div> 

  </div> <!-- >end of row -->

  <footer>
    <p>(c) Vítězslav Kremlík 2025 (kremlik@seznam.cz)</p>
  </footer>

</div> <!-- div class content -->
</body>
</html>
