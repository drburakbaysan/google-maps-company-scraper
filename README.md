# 🌐 Google Maps Company Scraper

![Google Maps Logo](https://upload.wikimedia.org/wikipedia/commons/9/99/Google_Maps_icon_%282020%29.svg)

## 📌 Project Overview

**Author:** Dr. Burak BAYSAN – [burak@baysan.tr](mailto:burak@baysan.tr)  
**GitHub:** [drburakbaysan](https://github.com/drburakbaysan)  
**Website:** [www.baysan.tr](https://www.baysan.tr)  
**Version:** 1.0  
**Date:** 2025-08-14  
**Project Name:** Google Maps Company Scraper  

This project is a **comprehensive PHP scraper** for extracting company data from **Google Maps**. It is designed to handle **large datasets**, up to **1000 results per request**, and displays results in a **live Ajax-powered table** with **search, sorting, pagination, and progress indicators**.  

It supports **any city worldwide**, manual city and company type input, and is built with **Bootstrap 5 dark-themed admin UI**.  

---

## ⚡ Features (Detailed)

- 🌆 **City Input:** Users can manually enter any city name.  
- 🏢 **Company Type Filter:**  
  - Top 100 most searched company types included  
  - Option for manual input  
- 🔢 **Result Limit:**  
  - User can select up to 1000 results per scrape  
  - Handles Google Maps **next_page_token** internally to bypass 60-results-per-request limit  
  - Live progress bar updates as each page is fetched  
- 📊 **Ajax Table:**  
  - Live search and dynamic sorting  
  - Pagination for large datasets  
  - Table updates in real-time while scraping continues  
- 💻 **UI / UX:**  
  - Bootstrap 5 based admin dashboard  
  - Dark mode theme  
  - Forms retain previous inputs for convenience  
  - Loading bar shows scraping percentage live  
- 🌐 **Data Fields Extracted:**  
  - Company Name  
  - Address  
  - Phone Number  
  - Website  
  - Coordinates (Latitude & Longitude)  
- 🛡️ **Security:**  
  - No CSV export; data displayed only in Ajax table  
  - API key embedded securely in PHP file  
  - No sensitive information stored server-side  

---

## 🧩 Technical Details

- **Scraping Mechanics:**  
  - Uses PHP cURL to query Google Maps Places API  
  - Supports multiple requests using **next_page_token**  
  - Aggregates data into PHP arrays for table display  
  - Handles **quota limits**: if exceeded, shows a **clear error in the UI**  
- **Live Loading / Progress:**  
  - Each page of results increments the progress bar  
  - Displays **percentage complete** in real-time  
  - Optimized for large queries (up to 1000 results)  
- **Error Handling:**  
  - Invalid API key → shows alert in table area  
  - API quota exceeded → table shows descriptive message and stops gracefully  
  - Network or cURL failures → retries 2 times before showing error  
- **Form Input Retention:**  
  - POST data persists in form fields after submission  
  - Dropdown retains last selected company type  
- **Limit Handling:**  
  - Default Google Maps API limit: 60 results per request  
  - The script automatically chains requests via **next_page_token**  
  - Users can set custom **total result limit** (max 1000)  
  - Exceeding daily quota may incur Google billing  

---

## 🛠️ Quick Setup

- PHP 7.4+ with cURL enabled  
- Web server (Apache/Nginx) or localhost  
- Google Maps API key with **Places API** enabled  

**Steps:**  

1. Clone repo  
2. Open `scraper.php`  
3. Embed your API key  
4. Open in browser  
5. Enter city name  
6. Select company type  
7. Choose number of results (max 1000)  
8. Click **Search**  
9. Watch **live loading bar**  
10. View results in **Ajax table**  

---

## ⚠️ Notes on API & Limits

- Google Maps Places API **free tier**: ~1000 requests/day  
- Default per-request limit: 60 results  
- Script handles **next_page_token** internally to fetch up to 1000 results per scrape  
- If quota exceeded:  
  - Ajax table displays **“Quota exceeded”** message  
  - No partial results are lost  
- Large scrapes may take **several minutes**; live progress bar shows completion  
- You can **increase result limit** but be aware of billing for extra quota  

---

## 🎨 UI & UX Details

- **Bootstrap 5 dark theme**  
- **Responsive layout** suitable for desktop and tablet  
- Live **progress bar** with percentage  
- Form input retention ensures convenience  
- Dropdown for **top 100 most searched company types**  
- Ajax table: search, sorting, pagination without page reload  

---

## 🔗 Useful Resources

- [Google Maps API Documentation](https://developers.google.com/maps/documentation)  
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.0/getting-started/introduction/)  
- [jQuery DataTables](https://datatables.net/)  
- [GitHub Profile – drburakbaysan](https://github.com/drburakbaysan)  

---

## 💡 Future Enhancements

- CSV / Excel export  
- Multi-city scraping  
- Filter by radius, ratings, reviews  
- User authentication & saved searches  
- Mobile-optimized UI  

---

## 📫 Contact

**Dr. Burak BAYSAN**  
- Email: [burak@baysan.tr](mailto:burak@baysan.tr)  
- GitHub: [drburakbaysan](https://github.com/drburakbaysan)  
- Website: [www.baysan.tr](https://www.baysan.tr)  

> Made with ❤️ by **Dr. Burak BAYSAN**
