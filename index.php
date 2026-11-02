<?php
/**
 * PickFix - Landing Page
 * 
 * Sri Lanka's 1st On-Demand Local Services, Garage Mechanics & 3D CAD Marketplace
 */

$pageTitle = "Sri Lanka's On-Demand Services, Garage Mechanics & 3D CAD Network";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-32">
    <!-- Background Glow and Grid Gradients -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-96 bg-gradient-to-tr from-brand-500/15 via-accent-500/10 to-transparent blur-3xl -z-10 pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Left Hero Column -->
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-700/80 text-xs font-semibold text-slate-300 shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-brand-500 animate-ping"></span>
                    <span>Live in Colombo, Kandy, Galle & Suburbs</span>
                    <span class="text-slate-500">•</span>
                    <span class="text-brand-400">15-Min Response</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-tight">
                    Instant Garage Mechanics, Local Trades & <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand-400 via-amber-300 to-indigo-400">3D CAD Modeling</span>
                </h1>

                <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto lg:mx-0 leading-relaxed font-normal">
                    The Sri Lankan on-demand super-app combining PickMe-style rapid dispatch for <strong>Garage Mechanics</strong> (Hybrid Diagnostics, Common Rail Diesel, Flatbed Towing) and licensed tradesmen with a digital studio for <strong>3D CAD engineering & rapid STL prototyping</strong>.
                </p>

                <!-- Action Buttons & Emergency SOS -->
                <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                    <!-- Emergency SOS Trigger Button -->
                    <a href="customer_dashboard.php?emergency=1" class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-7 py-4 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-base shadow-xl shadow-red-600/30 transition transform hover:-translate-y-0.5 sos-pulse">
                        <i class="fas fa-shield-alt text-xl animate-pulse"></i>
                        <span>EMERGENCY SOS DISPATCH</span>
                    </a>

                    <!-- Find Garage Mechanic Button -->
                    <a href="customer_dashboard.php?tab=mechanics" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-sm transition transform hover:-translate-y-0.5 shadow-lg shadow-amber-500/20">
                        <i class="fas fa-car-burst"></i>
                        <span>Find Garage Mechanics</span>
                    </a>

                    <!-- Request 3D Design Button -->
                    <a href="customer_dashboard.php?tab=3d_design" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-4 rounded-2xl bg-dark-800 hover:bg-dark-900 text-indigo-300 hover:text-white border border-indigo-500/40 font-bold text-sm transition shadow-lg">
                        <i class="fas fa-cube text-indigo-400"></i>
                        <span>Hire 3D / CAD Designer</span>
                    </a>
                </div>

                <!-- Trust & Monetization Stats -->
                <div class="grid grid-cols-3 gap-4 pt-6 border-t border-slate-800/80 max-w-lg mx-auto lg:mx-0 text-left">
                    <div>
                        <div class="text-2xl font-black text-white">2,400+</div>
                        <div class="text-[11px] text-slate-400">NIC-Verified Garages & Pros</div>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-amber-400">&lt; 15 Mins</div>
                        <div class="text-[11px] text-slate-400">Avg. Arrival ETA</div>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-emerald-400">PayHere</div>
                        <div class="text-[11px] text-slate-400">Genie & Cash Escrow</div>
                    </div>
                </div>

            </div>

            <!-- Right Hero Column: Interactive Quick Dispatch Card -->
            <div class="lg:col-span-5">
                <div class="relative bg-dark-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Quick Job Dispatch</span>
                        </div>
                        <span class="text-xs text-brand-400 font-semibold bg-brand-500/10 px-2 py-0.5 rounded border border-brand-500/20">GPS Radar Active</span>
                    </div>

                    <form action="customer_dashboard.php" method="GET" class="space-y-4">
                        <!-- Service Category Selector -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Select Service or Powertrain</label>
                            <div class="relative">
                                <select name="service" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-500 transition appearance-none">
                                    <optgroup label="Garage Mechanics & Automotive Rescue">
                                        <option value="mechanic" selected>🚗 Garage Mechanic: Hybrid, Diesel & Engine Repair</option>
                                    </optgroup>
                                    <optgroup label="Physical Trades & Breakdown">
                                        <option value="plumber">🔧 Plumber (Water leaks, pumps, drainage)</option>
                                        <option value="electrician">⚡ Electrician (Short circuits, solar wiring)</option>
                                        <option value="ac_repair">❄️ Inverter AC Repair & Gas Top-up</option>
                                    </optgroup>
                                    <optgroup label="Digital 3D & CAD Engineering">
                                        <option value="3d_design">🧊 3D Design & CAD (Render, STL, Blueprints)</option>
                                    </optgroup>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Location / Islandwide Address with OpenStreetMap Geocoding -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Breakdown / Service Location</label>
                            <div class="relative">
                                <i class="fas fa-map-marker-alt absolute left-4 top-3.5 text-brand-500 text-sm"></i>
                                <input type="text" name="location" placeholder="e.g. Colombo 03, Kollupitiya, Nugegoda, Kandy" value="Colombo 03 (GPS Auto-Detected)" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                            </div>
                        </div>

                        <!-- Emergency Checkbox -->
                        <div class="p-3 bg-red-950/30 border border-red-500/30 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="heroEmergency" name="emergency" value="1" class="w-4 h-4 rounded text-red-600 bg-slate-900 border-slate-700 focus:ring-red-500">
                                <label for="heroEmergency" class="text-xs text-red-300 font-bold cursor-pointer">Immediate Emergency Priority</label>
                            </div>
                            <span class="text-[10px] text-red-400 bg-red-500/20 px-2 py-0.5 rounded font-mono font-bold">&lt; 15 Min</span>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 font-extrabold text-sm shadow-lg shadow-brand-500/25 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fas fa-radar text-base"></i>
                            <span>Scan Radar for Available Garages & Pros</span>
                        </button>
                    </form>

                    <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                        <span><i class="fas fa-shield-alt text-brand-500 mr-1"></i> PayHere / Genie / Cash</span>
                        <span><i class="fas fa-bolt text-amber-400 mr-1"></i> Transparent 10% Platform Fee</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Dedicated Garage Mechanics & Roadside Rescue Hub Section -->
<section id="garages" class="py-20 bg-dark-900 border-t border-b border-slate-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between mb-16 gap-6">
            <div class="max-w-2xl space-y-3">
                <span class="text-xs font-bold text-amber-400 uppercase tracking-widest bg-amber-500/10 px-3 py-1 rounded-full border border-amber-500/20">
                    <i class="fas fa-car-burst mr-1"></i> Automotive Specialist Network
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                    Verified Garage Mechanics & Hybrid EV Diagnostic Centers
                </h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Filter certified garages across Western Province by engine type (Toyota/Honda Hybrid battery balancing, Common Rail Diesel overhauls, or flatbed towing recovery) with real-time ETA calculation.
                </p>
            </div>

            <a href="customer_dashboard.php?tab=mechanics" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-lg shadow-amber-500/20">
                <i class="fas fa-search"></i> Open Garage Mechanics Radar
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Garage Feature 1: Hybrid / EV -->
            <div class="bg-dark-950 border border-slate-800 hover:border-amber-500/60 rounded-2xl p-6 transition group">
                <div class="w-12 h-12 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition">
                    <i class="fas fa-charging-station"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Hybrid & Electric Vehicle Diagnostics</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    High-voltage cell balancing, inverter water pump testing, ABS actuator repair, and computerized OBD2 scanning for Toyota Aqua, Prius, Vezel, and Nissan Leaf.
                </p>
                <div class="text-[11px] font-mono text-amber-300 bg-slate-900 p-2 rounded-lg border border-slate-800">
                    Specialties: Cell Reconditioning, Inverter Overhaul
                </div>
            </div>

            <!-- Garage Feature 2: Common Rail Diesel & Heavy Engine -->
            <div class="bg-dark-950 border border-slate-800 hover:border-amber-500/60 rounded-2xl p-6 transition group">
                <div class="w-12 h-12 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition">
                    <i class="fas fa-gears"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Engine Overhauls & CRDI Diesel</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    High-pressure common rail diesel fuel injector calibration, turbocharger rebuilds, head gasket replacements, and computerized dyno mapping.
                </p>
                <div class="text-[11px] font-mono text-amber-300 bg-slate-900 p-2 rounded-lg border border-slate-800">
                    Specialties: CRDI Injectors, Turbocharger Servicing
                </div>
            </div>

            <!-- Garage Feature 3: Emergency Flatbed Towing -->
            <div class="bg-dark-950 border border-slate-800 hover:border-amber-500/60 rounded-2xl p-6 transition group">
                <div class="w-12 h-12 rounded-xl bg-red-500/15 text-red-400 flex items-center justify-center text-xl mb-4 group-hover:scale-110 transition">
                    <i class="fas fa-truck-pickup"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">24/7 Hydraulic Flatbed Towing</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Islandwide breakdown recovery, Southern/Central expressway rescue, wheel-lift towing, accident vehicle body repair, and insurance claim coordination.
                </p>
                <div class="text-[11px] font-mono text-red-300 bg-slate-900 p-2 rounded-lg border border-slate-800">
                    Specialties: Expressway Towing, Hydraulic Flatbed
                </div>
            </div>
        </div>

    </div>
</section>

<!-- 3D Design & CAD Studio Showcase (Digital Fabrication) -->
<section id="cad-studio" class="py-24 relative overflow-hidden">
    <div class="absolute right-0 top-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-600/10 blur-3xl rounded-full -z-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between mb-16 gap-6">
            <div class="max-w-2xl space-y-3">
                <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest bg-indigo-500/10 px-3 py-1 rounded-full border border-indigo-500/20">
                    <i class="fas fa-cube mr-1"></i> Digital Fabrication & CGI
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                    Professional 3D Design & CAD Engineering
                </h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Collaborate directly with Sri Lanka's leading 3D visualizers and mechanical design engineers. Submit your reference photos or dimensions, and download production-ready 3D files.
                </p>
            </div>

            <a href="customer_dashboard.php?tab=3d_design" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider transition shadow-lg shadow-indigo-600/25">
                <i class="fas fa-upload"></i> Submit 3D Project Spec
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-dark-900 border border-slate-800 rounded-2xl p-6 relative group hover:border-indigo-500/60 transition duration-200">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl mb-4">
                    <i class="fas fa-couch"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Interior & Architectural CGI</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Photorealistic 4K interior renderings for luxury apartments, villas, retail outlets, and commercial spaces.
                </p>
                <div class="text-[11px] font-mono text-indigo-300 bg-indigo-950/40 p-2 rounded-lg border border-indigo-500/20">
                    Outputs: 4K JPG, PNG, Blender Cycles
                </div>
            </div>

            <div class="bg-dark-900 border border-slate-800 rounded-2xl p-6 relative group hover:border-indigo-500/60 transition duration-200">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl mb-4">
                    <i class="fas fa-shapes"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Product Prototyping (STL)</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Watertight 3D printable meshes optimized for FDM, SLA, and SLS printers. Rapid turnaround for hardware startups.
                </p>
                <div class="text-[11px] font-mono text-indigo-300 bg-indigo-950/40 p-2 rounded-lg border border-indigo-500/20">
                    Outputs: .STL, .OBJ, 3MF Print Files
                </div>
            </div>

            <div class="bg-dark-900 border border-slate-800 rounded-2xl p-6 relative group hover:border-indigo-500/60 transition duration-200">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl mb-4">
                    <i class="fas fa-drafting-compass"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Mechanical CAD Blueprints</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Parametric solid modeling, sheet metal parts, CNC machining specs, and tolerance dimensioning in SolidWorks & Fusion 360.
                </p>
                <div class="text-[11px] font-mono text-indigo-300 bg-indigo-950/40 p-2 rounded-lg border border-indigo-500/20">
                    Outputs: .STEP, .IGES, .SLDPRT
                </div>
            </div>

            <div class="bg-dark-900 border border-slate-800 rounded-2xl p-6 relative group hover:border-indigo-500/60 transition duration-200">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl mb-4">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-2">Architectural BIM Modeling</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                    Converting hand-drawn 2D sketches or municipal approval drawings into comprehensive 3D Revit models & structural views.
                </p>
                <div class="text-[11px] font-mono text-indigo-300 bg-indigo-950/40 p-2 rounded-lg border border-indigo-500/20">
                    Outputs: .DWG, .RVT, IFC
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Live Map Preview (Free OpenStreetMap Tiles) -->
<section class="py-16 bg-dark-900/40 border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-dark-950 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 mb-6 border-b border-slate-800 gap-4">
                <div>
                    <h3 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="fas fa-satellite-dish text-brand-500"></i>
                        PickFix Active Pro & Garage Radar: Greater Colombo
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Real-time GPS telemetry showing verified on-duty technicians, garages, and online 3D CAD studios.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> OpenStreetMap Active
                    </span>
                    <a href="customer_dashboard.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-brand-500 hover:bg-brand-600 text-slate-950 transition">
                        Open Full Live Map
                    </a>
                </div>
            </div>

            <!-- Leaflet Map Container -->
            <div id="landingRadarMap" class="w-full h-80 rounded-2xl overflow-hidden border border-slate-800 z-10"></div>
        </div>
    </div>
</section>

<!-- Monetization & Escrow Guarantee Section -->
<section class="py-16 bg-gradient-to-r from-emerald-950/40 via-dark-950 to-indigo-950/40 border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center sm:text-left">
            <div class="space-y-2 p-6 bg-dark-900 border border-slate-800 rounded-2xl">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-lg mb-3">
                    <i class="fas fa-wallet"></i>
                </div>
                <h4 class="text-sm font-bold text-white">Transparent 10% Platform Fee</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    PickFix retains a fixed 10% platform facilitation commission. 90% goes straight to the hardworking local tradesperson or 3D freelancer.
                </p>
            </div>

            <div class="space-y-2 p-6 bg-dark-900 border border-slate-800 rounded-2xl">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-lg mb-3">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h4 class="text-sm font-bold text-white">Milestone & Delivery Escrow</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    For 3D CAD modeling, funds are held securely until you download and verify the watertight STL file or photorealistic render.
                </p>
            </div>

            <div class="space-y-2 p-6 bg-dark-900 border border-slate-800 rounded-2xl">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg mb-3">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h4 class="text-sm font-bold text-white">PayHere, Genie & Cash</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Pay seamlessly with Visa/MasterCard, Dialog Genie wallet, or directly settle in-person with cash on completion.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Leaflet Map Initialization for Colombo Radar (Free OpenStreetMap Tiles) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('landingRadarMap');
    if (!mapElement) return;

    const colomboLat = 6.9271;
    const colomboLng = 79.8612;
    const map = L.map('landingRadarMap', {
        zoomControl: true,
        scrollWheelZoom: false
    }).setView([colomboLat, colomboLng], 12);

    // Free, Open-Source OpenStreetMap Tile Layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Mock live providers on the map
    const mockPros = [
        { name: "Kasun Jayasuriya", cat: "Plumbing Pro", lat: 6.9271, lng: 79.8612, icon: "wrench", color: "#10b981" },
        { name: "Apex Hybrid Auto Clinic", cat: "Hybrid & EV Diagnostics", lat: 6.8905, lng: 79.8735, icon: "car-wrench", color: "#f59e0b" },
        { name: "Lanka Heavy Recovery", cat: "24/7 Flatbed Towing", lat: 6.9540, lng: 79.9120, icon: "truck-pickup", color: "#ef4444" },
        { name: "Roshan Silva", cat: "3D CAD & ArchViz Studio", lat: 6.9034, lng: 79.8646, icon: "cube", color: "#6366f1" },
        { name: "Nimal Fernando", cat: "Solar & Electrical", lat: 6.8722, lng: 79.8828, icon: "bolt", color: "#f59e0b" },
        { name: "Dinesh Weerasinghe", cat: "Inverter AC Tech", lat: 6.9147, lng: 79.8778, icon: "fan", color: "#06b6d4" }
    ];

    mockPros.forEach(pro => {
        const customIcon = L.divIcon({
            className: 'custom-radar-pin',
            html: `
                <div style="background-color: ${pro.color}; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(0,0,0,0.4); border: 2px solid #ffffff;">
                    <i class="fas fa-${pro.icon}" style="color: #ffffff; font-size: 13px;"></i>
                </div>
            `,
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });

        L.marker([pro.lat, pro.lng], { icon: customIcon })
            .addTo(map)
            .bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px; color: #0f172a;">
                    <strong style="font-size: 13px;">${pro.name}</strong><br>
                    <span style="color: #6366f1; font-weight: 600;">${pro.cat}</span><br>
                    <span style="color: #059669;">● Online & Verified</span>
                </div>
            `);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
