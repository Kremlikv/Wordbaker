
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
            <label>Available days</label>
            <div class="days">
              <label><input type="checkbox" name="days[]" value="Mon"> Mon</label>
              <label><input type="checkbox" name="days[]" value="Tue"> Tue</label>
              <label><input type="checkbox" name="days[]" value="Wed"> Wed</label>
              <label><input type="checkbox" name="days[]" value="Thu"> Thu</label>
              <label><input type="checkbox" name="days[]" value="Fri"> Fri</label>
              <label><input type="checkbox" name="days[]" value="Sat"> Sat</label>
              <label><input type="checkbox" name="days[]" value="Sun"> Sun</label>
            </div>
          </div>
        </div>

        <div class="row">
          <div>
            <label for="start_date">Available from (date)</label>
            <input type="date" id="start_date" name="start_date">
          </div>
          <div>
            <label for="end_date">Available until (date)</label>
            <input type="date" id="end_date" name="end_date">
          </div>
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
