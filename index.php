
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
    <summary>Translations</summary>
    <div class="tile-body">
      <p>Texttext</p>
    </div>
  </details>

  <details class="tile">
    <summary>Classes</summary>
    <div class="tile-body">
      <form class="enquiry-form" method="post" action="submit_interest.php">
        <div class="row">
          <div>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" placeholder="Your name" required>
          </div>
          <div>
            <label for="contact">Contact (email or phone)</label>
            <input type="text" id="contact" name="contact" placeholder="e.g., jana@example.com or +420…" required>
          </div>
        </div>



        <div class="row">
            <div>
              <label for="level">Level</label>
              <select id="level" name="level" required>
                <option value="" selected disabled>Select level</option>
                <option>A1</option><option>A2</option>
                <option>B1</option><option>B2</option>
                <option>C1</option><option>C2</option>
              </select>
            </div>

            <div>
              <label>Available days & times</label>
              <div class="days">
                <div class="day-row">
                  <input type="checkbox" id="mon" name="days[]" value="Mon">
                  <label for="mon">Mon</label>
                  <input type="text" name="times[Mon]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="tue" name="days[]" value="Tue">
                  <label for="tue">Tue</label>
                  <input type="text" name="times[Tue]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="wed" name="days[]" value="Wed">
                  <label for="wed">Wed</label>
                  <input type="text" name="times[Wed]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="thu" name="days[]" value="Thu">
                  <label for="thu">Thu</label>
                  <input type="text" name="times[Thu]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="fri" name="days[]" value="Fri">
                  <label for="fri">Fri</label>
                  <input type="text" name="times[Fri]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="sat" name="days[]" value="Sat">
                  <label for="sat">Sat</label>
                  <input type="text" name="times[Sat]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
                <div class="day-row">
                  <input type="checkbox" id="sun" name="days[]" value="Sun">
                  <label for="sun">Sun</label>
                  <input type="text" name="times[Sun]" placeholder="e.g., 8:00–10:00, 18:00–20:00">
                </div>
              </div>
            </div>
          </div>

          <div>
            <label for="start_date">Available from (date)</label>
            <input type="date" id="start_date" name="start_date">
          </div>


        <div>
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" placeholder="Goals, topics, online/in-person, time preferences…"></textarea>
        </div>

        <div>
          <button class="enquiry-submit" type="submit">Send application</button>
        </div>
      </form>
    </div>
  </details>

  <details class="tile">
    <summary>App</summary>
    <div class="tile-body">
      <p>Texttext</p>
    </div>
  </details>

  <details class="tile">
    <summary>Tour Guide</summary>
    <div class="tile-body">
      <p>Texttext</p>
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
