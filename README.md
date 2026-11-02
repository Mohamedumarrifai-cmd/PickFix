# PickFix — Sri Lanka's On-Demand Services, Garage Mechanics & 3D CAD Marketplace

**PickFix** is a full-stack on-demand service and digital fabrication web platform built specifically for the Sri Lankan market. It combines the rapid GPS dispatch model of **PickMe** (for garage mechanics, plumbers, electricians, and AC technicians) with a specialized digital freelance studio for **3D Design & CAD Engineering** (photorealistic 3D interior renders, watertight STL 3D printing models, SolidWorks blueprints, and BIM models).

---

## 🚀 What's New in This Release

1. **Expanded Database Architecture (`schema.sql`):**
   - **`messages` Table:** Real-time customer-to-provider chat logs per active booking.
   - **`reviews` Table:** 5-star rating system with customer feedback and dynamic provider score recalculation.
   - **`transactions` Table:** Monetization tracking for local payment gateways (**PayHere**, **Dialog Genie**, and **Cash on Delivery**) with 10% platform commission accounting.
   - **Garage Mechanics Integration:** Expanded `providers` table with `workshop_name`, `engine_types` (Hybrid/EV, Diesel, Petrol), `specialties`, and `towing_available` badges.

2. **Dedicated Garage Mechanics Search & Rescue Radar:**
   - Filter by powertrain: **Hybrid & Electric (EV / Battery Cell Balancing)**, **Common Rail Diesel (CRDI / Turbo)**, **Petrol EFI**.
   - Filter by specialization: **Engine Overhaul**, **Hybrid Inverter Diagnostics**, **Bodywork & Accident Repair**, **Hydraulic Flatbed Towing**.
   - Proximity & Transit ETA calculation: Real-time Haversine formula calculation in PHP and JS displaying distance in kilometers and estimated transit time (e.g. `1.8 km • 7 mins ETA`).

3. **Leaflet.js with Zero-Cost Public APIs:**
   - **OpenStreetMap Tile Layers:** 100% free, open-source cartography with no proprietary API billing keys required.
   - **Nominatim Geocoding API:** Type any Sri Lankan city or street (e.g. *Kollupitiya*, *Nugegoda*, *Kelaniya*, *Kandy*, *Galle*) to dynamically re-center maps and calculate routing distance.

4. **Monetization & Commission Flow (`/api/process_payment.php`):**
   - Retains a transparent **10% Platform Commission Fee** and credits **90% Net Provider Payout**.
   - Generates unique transaction references (`TXN-PH-...` or `TXN-GEN-...`) and provides clear financial breakdowns.

5. **Real-Time AJAX Chat & 5-Star Reviews:**
   - Customer and provider/mechanic can chat directly inside the active job tracker with unread message badges.
   - Interactive 5-star rating submission upon job completion.

---

## 🔑 Pre-Configured Demo Accounts

All test accounts share the password: **`password123`**

| Role / Category | Workshop / Name | Email | Default Dashboard |
| :--- | :--- | :--- | :--- |
| **Client / Customer** | Kavinda Perera | `customer@pickfix.lk` | `customer_dashboard.php` |
| **Hybrid Garage Pro** | Apex Hybrid Auto Clinic & EV Tech | `priyantha.hybrid@pickfix.lk` | `provider_dashboard.php` |
| **Heavy Towing & Rescue** | Lanka Heavy Recovery & 24/7 Towing | `sunil.towing@pickfix.lk` | `provider_dashboard.php` |
| **Diesel & Engine Specialist** | Precision Motor Works & ECU Tuning | `rauf.tuning@pickfix.lk` | `provider_dashboard.php` |
| **3D CAD & CGI Designer** | Colombo 3D Prototyping & CAD Lab | `roshan.3d@pickfix.lk` | `provider_dashboard.php` |
| **Emergency Plumber** | Lanka Quick Flow Plumbing | `kasun.plumber@pickfix.lk` | `provider_dashboard.php` |
| **Certified Electrician** | ElectroTech Power Solutions | `nimal.electrician@pickfix.lk` | `provider_dashboard.php` |

*(Note: The login screen includes 1-click test buttons to instantly populate credentials).*

---

## 📂 Architecture & Directory Structure

```
d:\xampp\htdocs\PickFix\
│
├── config/
│   └── database.php                # PDO singleton connection, commission & formatting helpers
│
├── includes/
│   ├── header.php                  # Tailwind CSS, FontAwesome 6, Leaflet, session navigation
│   └── footer.php                  # Sri Lankan hotlines (1990, 119), coverage, copyright
│
├── api/
│   ├── create_booking.php          # POST: Booking creation & reference spec upload
│   ├── get_nearby_providers.php    # GET: Real-time Haversine proximity, ETA & garage filters
│   ├── upload_design_file.php      # POST: 3D deliverable upload (.stl, .zip, .step, .jpg)
│   ├── get_booking_status.php      # GET: Live AJAX polling for status, payments & unread chat
│   ├── update_booking_status.php   # POST: Provider accept, start work, complete, cancel
│   ├── update_provider_status.php  # POST: Toggle online/offline & broadcast GPS coordinates
│   ├── process_payment.php         # POST: PayHere / Genie / Cash with 10% platform fee
│   ├── send_message.php            # POST: Real-time customer-provider chat message
│   ├── get_messages.php            # GET: Chat history & live message stream
│   └── submit_review.php           # POST: 5-star customer review & dynamic provider rating
│
├── uploads/
│   ├── .htaccess                   # Prohibits PHP script execution in upload storage
│   ├── references/                 # Customer uploaded reference sketches & PDFs
│   └── deliverables/               # Designer uploaded 3D meshes, CAD files & renders
│
├── index.php                       # Landing page with garage filters & emergency SOS
├── login.php                       # Authentication with 1-click demo logins & role redirect
├── register.php                    # Role selection (Customer vs NIC-verified Garage/Pro)
├── customer_dashboard.php          # Garage radar, Nominatim search, 3D lab, chat & PayHere
├── provider_dashboard.php          # Job radar, active chat, deliverable studio & revenue ledger
├── logout.php                      # Session clearing & safe redirect
├── schema.sql                      # Complete MySQL migration script with sample seeds
└── setup_db.php                    # Web & CLI automated database installer tool
```

---

## 🛠️ Step-by-Step Installation

1. Start **Apache** and **MySQL** in your XAMPP Control Panel.
2. Open your web browser and navigate to:
   ```
   http://localhost/PickFix/setup_db.php
   ```
3. Click **"Initialize Database Now"** (or run `php setup_db.php` in terminal).
   This initializes:
   - `users` (customers and verified mechanics/designers)
   - `providers` (with workshop names, engine types, towing trucks, and ratings)
   - `bookings` (emergency SOS, hybrid battery checks, and 3D CAD projects)
   - `messages` (seeded live chat conversation samples)
   - `reviews` (5-star ratings and customer testimonials)
   - `transactions` (PayHere / Genie payment logs with 10% commission calculations)
4. Open the web app at:
   ```
   http://localhost/PickFix/
   ```
