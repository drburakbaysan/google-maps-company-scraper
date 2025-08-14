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
 * the Google Maps Places API's next_page_token and a grid system to bypass the 
 * 60 results limit. Results are displayed in a live Ajax-powered table with 
 * search, sorting, and pagination.
 *
 * Important Technical Notes:
 * - API key is embedded in this file for simplicity.
 * - No CSV export; results stay in the Ajax table.
 * - Handles Google Maps API quota and limit gracefully.
 * ===========================================================================
 */

// ===============================
// Configuration Section
// ===============================

// Google Maps API Key - Replace with your own key
$google_api_key = "Your Google API Key";
// Maximum results allowed for a single request
$max_results_default = 1000;

// Top company types for dropdown selection
$top_company_types = array(
    "restaurant","cafe","pharmacy","hospital","hotel","school","bank","supermarket",
    "bakery","clinic","bar","gym","gas station","library","shopping mall",
    "laundry","car repair","beauty salon","movie theater","museum","park","church",
    "mosque","police","fire station","train station","bus station","airport",
    "dentist","vet","painter","plumber","electrician","lawyer","insurance agency",
    "real estate","construction","furniture store","clothing store","electronics store",
    "pet store","hair salon","spa","doctor","optician","toy store",
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

// ===============================
// Backend Section (AJAX Handler)
// ===============================

// Handle AJAX POST requests for scraping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scrape') {
    header('Content-Type: application/json; charset=UTF-8');

    // Secure input handling
    $district     = trim($_POST['district'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $company_type = trim($_POST['company_type'] ?? '');
    $limit        = (int)($_POST['result_limit'] ?? $max_results_default);

    // Enforce result limit boundaries
    if ($limit < 1) $limit = 1;
    if ($limit > $max_results_default) $limit = $max_results_default;

    if (!$city || !$company_type) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Please enter both city and company type.'
        ]);
        exit;
    }

    try {
        // Main scraping logic
        $results = scrapeGoogleMapsUnlimited($district, $city, $company_type, $limit, $google_api_key);
        if (isset($results['error'])) {
            echo json_encode(['success' => false, 'error' => $results['error']]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'count'   => count($results),
            'data'    => $results
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Server error: ' . $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Fetch phone number and website from Place Details API for a given place_id.
 * Handles API quota gracefully.
 *
 * @param string $place_id
 * @param string $api_key
 * @return array
 */
function getPlaceDetails($place_id, $api_key) {
    $url = "https://maps.googleapis.com/maps/api/place/details/json?"
         . http_build_query([
             'place_id' => $place_id,
             'fields'   => 'formatted_phone_number,website',
             'key'      => $api_key
         ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200 || !$response) return ['phone' => '', 'website' => ''];

    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? '') === 'OVER_QUERY_LIMIT') {
        // On quota overrun, return empty info
        return ['phone' => '', 'website' => ''];
    }
    return [
        'phone'   => $data['result']['formatted_phone_number'] ?? '',
        'website' => $data['result']['website'] ?? ''
    ];
}

/**
 * Scrape Google Maps using grid-based pagination to overcome the 60-results limit.
 * Uses multiple geo-points and combines results, avoiding duplicates.
 * Each company gets details via Place Details API.
 *
 * @param string $district
 * @param string $city
 * @param string $company_type
 * @param int $limit
 * @param string $api_key
 * @return array
 */
function scrapeGoogleMapsUnlimited($district, $city, $company_type, $limit, $api_key) {
    $results = [];
    $fetched = 0;
    $unique_places = [];
    $max_per_search = 60; // Google Maps Text Search API returns max 60 per search

    // Find lat/lng center of search area using Geocoding API
    $location_query = $district ? "$district, $city" : $city;
    $center = getGeoLocation($location_query, $api_key);
    if (!$center) return ['error' => "Could not obtain location coordinates."];

    // Calculate grid size for area sampling (e.g. 2x2, 3x3)
    $grid_size = ceil(max(1, sqrt($limit / $max_per_search)));
    $lat_step = 0.02; // latitude increment for grid points
    $lng_step = 0.02; // longitude increment for grid points

    $start_lat = $center['lat'] - ($lat_step * $grid_size) / 2;
    $start_lng = $center['lng'] - ($lng_step * $grid_size) / 2;

    for ($i = 0; $i < $grid_size; $i++) {
        for ($j = 0; $j < $grid_size; $j++) {
            // Calculate grid center
            $lat = $start_lat + $i * $lat_step;
            $lng = $start_lng + $j * $lng_step;

            // Scrape companies at this grid location
            $places = scrapeGoogleMapsGrid($company_type, $lat, $lng, $district, $city, $max_per_search, $api_key);

            foreach ($places as $place) {
                // Avoid duplicate place_ids
                if (!isset($place['place_id']) || isset($unique_places[$place['place_id']])) continue;
                $unique_places[$place['place_id']] = true;

                // Fetch phone and website details for this place
                $details = getPlaceDetails($place['place_id'], $api_key);
                usleep(120000); // Be polite with the API, avoid hitting rate limits

                $results[] = [
                    'name'    => $place['name'] ?? '',
                    'address' => $place['formatted_address'] ?? '',
                    'phone'   => $details['phone'] ?? '',
                    'website' => $details['website'] ?? '',
                    'lat'     => $place['geometry']['location']['lat'] ?? '',
                    'lng'     => $place['geometry']['location']['lng'] ?? ''
                ];
                $fetched++;
                if ($fetched >= $limit) break 2;
            }
            if ($fetched >= $limit) break 2;
        }
    }
    return $results;
}

/**
 * Search for companies in a grid cell using the Google Maps Text Search API.
 * Each cell focuses on a specific geo-location and radius.
 *
 * @param string $company_type
 * @param float $lat
 * @param float $lng
 * @param string $district
 * @param string $city
 * @param int $limit
 * @param string $api_key
 * @return array
 */
function scrapeGoogleMapsGrid($company_type, $lat, $lng, $district, $city, $limit, $api_key) {
    $results = [];
    $fetched = 0;
    $next_page_token = '';

    // Build search query and location/radius for more precise results
    $location_query = $district ? "$company_type in $district, $city" : "$company_type in $city";

    do {
        $params = [
            'query'    => $location_query,
            'location' => $lat . ',' . $lng,
            'radius'   => 2000, // 2km (Google max 50km but smaller radius is more precise)
            'key'      => $api_key
        ];
        if ($next_page_token) {
            $params['pagetoken'] = $next_page_token;
            usleep(600000); // Wait for next_page_token to activate
        }

        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?" . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200 || !$response) break;

        $data = json_decode($response, true);
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if ($status !== 'OK' && $status !== 'ZERO_RESULTS') break;
        if (empty($data['results']) || !is_array($data['results'])) break;

        foreach ($data['results'] as $place) {
            $results[] = $place;
            $fetched++;
            if ($fetched >= $limit) break 2;
        }

        $next_page_token = $data['next_page_token'] ?? '';
        if ($next_page_token) usleep(1200000); // Wait for next_page_token
    } while ($next_page_token);

    return $results;
}

/**
 * Get latitude and longitude for an address using Google Geocoding API.
 *
 * @param string $address
 * @param string $api_key
 * @return array|null
 */
function getGeoLocation($address, $api_key) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query([
        'address' => $address,
        'key'     => $api_key
    ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200 || !$response) return null;

    $data = json_decode($response, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['geometry']['location'])) {
        return null;
    }
    return $data['results'][0]['geometry']['location'];
}

// ===============================
// Frontend Section (UI)
// ===============================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Google Maps Company Scraper (AJAX)</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- DataTables + Bootstrap 5 -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">

<style>
    body { background-color:#0f1115; color:#e9ecef; }
    .card { background: #141824; border: 1px solid rgba(255,255,255,.06); }
    .form-label { color: white; font-weight: 600; }
    .spinner-overlay{
        position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
        background: rgba(0,0,0,.55); z-index: 1050;
    }
    .link-muted { color:#9fb3c8; text-decoration:none; }
    .link-muted:hover { color:red; text-decoration:underline; }
    .progress { height: 8px; background: #1c2333; }
    .progress-bar { background: linear-gradient(90deg,#0d6efd,#6f42c1); }
    .badge-soft { background: rgba(13,110,253,.15); color:#9bbcff; }
</style>
</head>
<body>
<div class="spinner-overlay" id="spinnerOverlay">
    <div class="text-center">
        <div class="spinner-border" role="status" style="width:3rem;height:3rem;"></div>
        <div class="mt-3">Searching, please wait…</div>
        <div class="progress mt-3" style="width:260px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 20%;"></div>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="m-0">🌐 Google Maps Company Scraper</h3>
        <span class="badge rounded-pill text-bg-dark">AJAX + Details API</span>
    </div>

    <div class="card p-3 mb-4">
        <form id="scrapeForm" class="row gy-3 gx-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">District (Optional)</label>
                <input type="text" name="district" class="form-control" placeholder="E.g.: Kadıköy">
            </div>
            <div class="col-md-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" placeholder="E.g.: Istanbul" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Company Type</label>
                <select name="company_type" class="form-select" required>
                    <option value="">Select…</option>
                    <?php foreach($top_company_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Results</label>
                <input type="number" name="result_limit" class="form-control" min="1" max="1000" value="<?php echo (int)$max_results_default; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" id="btnSearch" class="btn btn-primary w-100">
                    Search
                </button>
            </div>
            <div class="col-12">
                <small class="text-secondary">
                    Tip: If district is left blank, search will be performed for the whole city. 
                    Phone & website are fetched via Place Details API (additional requests).
                </small>
            </div>
        </form>
    </div>

    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <span class="me-2">Results</span>
                <span class="badge badge-soft" id="resultCount">0 records</span>
            </div>
            <div id="exportButtons"></div>
        </div>

        <div class="table-responsive">
            <table id="resultsTable" class="table table-dark table-striped table-hover align-middle nowrap" style="width:100%;">
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
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- JS: jQuery, Bootstrap, DataTables + Extensions -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- DataTables Buttons + deps -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
(function(){
    let table = null;
    let progressTimer = null;

    // Start spinner and fake progress bar for UX
    function startSpinner(){
        $('#spinnerOverlay').fadeIn(120);
        const bar = $('#progressBar');
        let w = 20;
        progressTimer = setInterval(() => {
            w = (w >= 90) ? 30 : (w + Math.floor(Math.random()*10));
            bar.css('width', w + '%');
        }, 400);
    }
    // Stop spinner and reset progress bar
    function stopSpinner(){
        clearInterval(progressTimer);
        $('#progressBar').css('width', '100%');
        setTimeout(() => {
            $('#spinnerOverlay').fadeOut(150);
            $('#progressBar').css('width', '20%');
        }, 200);
    }
    // Initialize DataTable or clear if already exists
    function initTable(){
        if(table){
            table.clear().draw();
            return;
        }
        table = $('#resultsTable').DataTable({
            responsive: true,
            deferRender: true,
            pageLength: 25,
            lengthMenu: [10,25,50,100,250,500],
            dom: "<'row'<'col-sm-12'tr>>" +
                 "<'row mt-3'<'col-md-6'i><'col-md-6 d-flex justify-content-end'p>>",
        });

        // Place export buttons in custom container
        new $.fn.dataTable.Buttons(table, {
            buttons: [
                { extend: 'copyHtml5', text: 'Copy' },
                { extend: 'csvHtml5',  text: 'CSV'  },
                { extend: 'excelHtml5',text: 'Excel'},
                { extend: 'pdfHtml5',  text: 'PDF'  },
                { extend: 'print',     text: 'Print'}
            ]
        });
        table.buttons().container().appendTo('#exportButtons');
    }

    // Fill DataTable with data
    function fillTable(rows){
        initTable();
        const formatted = rows.map((r, idx) => {
            const phone = r.phone ? `<a class="link-muted" href="tel:${r.phone.replace(/[^0-9+]/g,'')}">${r.phone}</a>` : '-';
            const web = r.website ? `<a class="link-muted" href="${r.website}" target="_blank" rel="noopener">Website</a>` : '-';
            return [
                idx + 1,
                r.name || '',
                r.address || '',
                phone,
                web,
                r.lat || '',
                r.lng || ''
            ];
        });
        table.clear().rows.add(formatted).draw();
        $('#resultCount').text(rows.length + ' records');
    }

    // Handle form submit
    $('#scrapeForm').on('submit', function(e){
        e.preventDefault();
        const $btn = $('#btnSearch');
        $btn.prop('disabled', true);

        startSpinner();

        // AJAX request
        const formData = $(this).serializeArray();
        formData.push({name:'action', value:'scrape'});

        $.ajax({
            method: 'POST',
            url: '', // same file
            data: formData,
            dataType: 'json'
        }).done(function(res){
            if(!res || !res.success){
                const msg = (res && res.error) ? res.error : 'Unknown error.';
                showAlert(msg, 'danger');
                fillTable([]);
                return;
            }
            fillTable(res.data || []);
            if ((res.data || []).length === 0) {
                showAlert('No results found.', 'warning');
            } else {
                showAlert('Search completed. ' + res.count + ' records found.', 'success');
            }
        }).fail(function(xhr){
            const msg = xhr.responseJSON?.error || ('HTTP ' + xhr.status + ' error.');
            showAlert(msg, 'danger');
            fillTable([]);
        }).always(function(){
            stopSpinner();
            $btn.prop('disabled', false);
        });
    });

    // Show Bootstrap alert message
    function showAlert(message, type){
        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show mt-3" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        $('.card').first().after(alert);
        setTimeout(()=>{ alert.alert('close'); }, 7000);
    }

    // Initial table setup
    initTable();
})();
</script>
</body>
</html>
