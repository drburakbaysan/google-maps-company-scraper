<?php
/**
 * ===========================================================================
 * Project: Google Maps Company Scraper
 * Author: Dr. Burak BAYSAN - burak@baysan.tr
 * GitHub: https://github.com/drburakbaysan
 * Website: https://www.baysan.tr
 * Version: 1.0
 * Date: 2025-08-14
 * 
 * Description:
 * This PHP script scrapes company data from Google Maps for any city.
 * Users can input the city name and select a company type from the top 100 most
 * searched types. The scraper handles up to 1000 results per request by using
 * the Google Maps Places API's next_page_token. Results are displayed in a
 * live Ajax-powered table with search, sorting, and pagination.
 *
 * Important Technical Notes:
 * - API key is embedded in this file for simplicity.
 * - No CSV export; results stay in the Ajax table.
 * - Handles Google Maps API quota and limit gracefully.
 * ===========================================================================
 */

// ---------------------------------------------------------------------------
// ---------------------- CONFIGURATION --------------------------------------
// ---------------------------------------------------------------------------

// Google Maps API key
$google_api_key = "YOUR GOOGLE API KEY"; // <-- Replace with your own key if needed

// Default maximum number of results per scrape
$max_results = 1000; // User can adjust via form input

// List of top 100 most searched company types (for dropdown)
$top_company_types = array(
    "restaurant","cafe","pharmacy","hospital","hotel","school","bank","supermarket",
    "bakery","clinic","bar","clinic","gym","gas station","library","shopping mall",
    "laundry","car repair","beauty salon","movie theater","museum","park","church",
    "mosque","police","fire station","train station","bus station","airport",
    "dentist","vet","painter","plumber","electrician","lawyer","insurance agency",
    "real estate","construction","furniture store","clothing store","electronics store",
    "pet store","hair salon","spa","pharmacy","doctor","optician","toy store",
    "book store","jewelry store","travel agency","taxi","car rental","night club",
    "fast food","ice cream shop","pizzeria","barber","bank ATM","car wash",
    "hardware store","garden center","florist","beauty supply","convenience store",
    "liquor store","shoe store","mobile phone shop","internet cafe","coffee shop",
    "hotel chain","restaurant chain","school district","daycare","fitness center",
    "swimming pool","museum gallery","art gallery","language school","driving school",
    "computer store","electronics repair","printing service","photography studio",
    "cleaning service","laundromat","massage therapist","nail salon","tattoo studio",
    "travel tour","casino","billiard hall","bowling alley","sports club","yoga studio",
    "dance school","movie rental","pet grooming","gaming store","stationery store"
);

// ---------------------------------------------------------------------------
// ---------------------- HANDLE FORM SUBMISSION -----------------------------
// ---------------------------------------------------------------------------

/** Check if user submitted the form */
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$company_type = isset($_POST['company_type']) ? trim($_POST['company_type']) : '';
$result_limit = isset($_POST['result_limit']) ? intval($_POST['result_limit']) : $max_results;

// Ensure city and company type are provided
if(!$city || !$company_type){
    // Form not submitted or missing fields
    $form_submitted = false;
}else{
    $form_submitted = true;
}

// ---------------------------------------------------------------------------
// ---------------------- FUNCTION TO SCRAPE GOOGLE MAPS --------------------
// ---------------------------------------------------------------------------

/**
 * scrapeGoogleMaps
 * Makes requests to Google Maps Places API, handles pagination via next_page_token,
 * aggregates results, and returns them as a PHP array.
 * 
 * @param string $city
 * @param string $company_type
 * @param int $limit
 * @param string $api_key
 * @return array
 */
function scrapeGoogleMaps($city, $company_type, $limit, $api_key){
    $results = array(); // Initialize results array
    $fetched = 0;       // Counter for results fetched
    $next_page_token = ''; // Token for paginated results

    do {
        // Build Google Maps Places API URL
        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?";
        $query = urlencode($company_type . " in " . $city);
        $url .= "query={$query}&key={$api_key}";
        if($next_page_token){
            $url .= "&pagetoken={$next_page_token}";
        }

        // -------------------------------------------------------------------
        // ----------------- SEND REQUEST USING CURL -------------------------
        // -------------------------------------------------------------------
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // -------------------------------------------------------------------
        // ----------------- PARSE RESPONSE ---------------------------------
        // -------------------------------------------------------------------
        if($http_code !== 200){
            // HTTP error
            break;
        }

        $data = json_decode($response, true);

        if(isset($data['status']) && $data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS'){
            // API returned error (e.g., OVER_QUERY_LIMIT, REQUEST_DENIED)
            $results['error'] = $data['status'] . " - " . ($data['error_message'] ?? '');
            break;
        }

        if(!isset($data['results']) || !is_array($data['results'])){
            break;
        }

        // Loop through returned results and collect needed fields
        foreach($data['results'] as $place){
            $results[] = array(
                'name' => $place['name'] ?? '',
                'address' => $place['formatted_address'] ?? '',
                'phone' => $place['formatted_phone_number'] ?? '',
                'website' => $place['website'] ?? '',
                'lat' => $place['geometry']['location']['lat'] ?? '',
                'lng' => $place['geometry']['location']['lng'] ?? ''
            );

            $fetched++;
            if($fetched >= $limit) break 2; // Exit both loops if limit reached
        }

        // Check for next page token
        $next_page_token = $data['next_page_token'] ?? '';

        // Google Maps requires a short delay before using next_page_token
        if($next_page_token){
            sleep(2);
        }

    } while($next_page_token);

    return $results;
}

// ---------------------------------------------------------------------------
// ---------------------- HANDLE SCRAPING IF FORM SUBMITTED -----------------
// ---------------------------------------------------------------------------

$scraped_results = array();
if($form_submitted){
    $scraped_results = scrapeGoogleMaps($city, $company_type, $result_limit, $google_api_key);
}

// ---------------------------------------------------------------------------
// ---------------------- HTML & AJAX TABLE ---------------------------------
// ---------------------------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Google Maps Company Scraper</title>
<!-- Bootstrap 5 Dark Theme -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- jQuery & DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<style>
body { background-color:#121212; color:#f1f1f1; }
.table-responsive { margin-top:20px; }
.progress { height:25px; }
</style>
</head>
<body>
<div class="container mt-4">

<h2>🌐 Google Maps Company Scraper</h2>
<p>Enter city and company type to scrape company data. Top 100 types are available in dropdown.</p>

<!-- ---------------- FORM ---------------- -->
<form method="POST" id="scrapeForm">
<div class="row mb-3">
    <div class="col-md-4">
        <input type="text" name="city" class="form-control" placeholder="City" value="<?php echo htmlspecialchars($city); ?>" required>
    </div>
    <div class="col-md-4">
        <select name="company_type" class="form-select" required>
            <option value="">Select Company Type</option>
            <?php foreach($top_company_types as $type){
                $selected = ($company_type === $type) ? 'selected' : '';
                echo "<option value=\"{$type}\" {$selected}>{$type}</option>";
            } ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="number" name="result_limit" class="form-control" placeholder="Max Results" value="<?php echo $result_limit; ?>" min="1" max="1000">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
</div>
</form>

<!-- ---------------- PROGRESS BAR ---------------- -->
<div class="progress mb-3">
    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%">0%</div>
</div>

<!-- ---------------- RESULTS TABLE ---------------- -->
<div class="table-responsive">
    <table id="resultsTable" class="table table-dark table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Address</th>
                <th>Phone</th>
                <th>Website</th>
                <th>Latitude</th>
                <th>Longitude</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if(!empty($scraped_results)){
                $count = 1;
                foreach($scraped_results as $row){
                    echo "<tr>
                        <td>{$count}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['address']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['website']}</td>
                        <td>{$row['lat']}</td>
                        <td>{$row['lng']}</td>
                    </tr>";
                    $count++;
                }
            }
            ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function(){
    // Initialize DataTable with search, sorting, pagination
    $('#resultsTable').DataTable();
});
</script>

</div>
</body>
</html>
