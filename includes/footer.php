<?php
/**
 * PickFix - Global Footer Template
 * 
 * Includes site map, emergency helplines, Sri Lanka coverage areas, and copyright notices.
 */
?>
    <!-- Global Footer -->
    <footer class="mt-auto bg-dark-900 border-t border-slate-800/80 pt-16 pb-10 text-slate-400 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 mb-12">
                
                <!-- Col 1: Brand & Overview -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-500 to-accent-500 flex items-center justify-center shadow-md">
                            <i class="fas fa-cube text-slate-950 text-lg font-black"></i>
                        </div>
                        <span class="text-2xl font-black text-white">Pick<span class="text-brand-500">Fix</span></span>
                        <span class="bg-brand-500/10 text-brand-400 text-[10px] font-bold px-2 py-0.5 rounded border border-brand-500/20">SRI LANKA</span>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-400 max-w-sm">
                        Sri Lanka's 1st hybrid on-demand platform. Connecting households and businesses to rapid verified local tradesmen within minutes, alongside an elite digital marketplace for 3D CAD modeling, photorealistic rendering, and rapid STL prototyping.
                    </p>
                    <div class="flex items-center gap-3 pt-2">
                        <div class="flex items-center gap-1.5 text-xs text-emerald-400">
                            <i class="fas fa-id-card"></i> 100% NIC Verified Pros
                        </div>
                        <span class="text-slate-700">•</span>
                        <div class="flex items-center gap-1.5 text-xs text-indigo-400">
                            <i class="fas fa-shield-alt"></i> Safe Milestone Escrow
                        </div>
                    </div>
                </div>

                <!-- Col 2: On-Demand Physical Services -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Physical Services</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="customer_dashboard.php?service=plumber" class="hover:text-brand-500 transition"><i class="fas fa-wrench w-4 text-slate-500"></i> Emergency Plumber</a></li>
                        <li><a href="customer_dashboard.php?service=electrician" class="hover:text-brand-500 transition"><i class="fas fa-bolt w-4 text-slate-500"></i> Certified Electrician</a></li>
                        <li><a href="customer_dashboard.php?service=mechanic" class="hover:text-brand-500 transition"><i class="fas fa-car-crash w-4 text-slate-500"></i> Roadside Breakdown</a></li>
                        <li><a href="customer_dashboard.php?service=ac_repair" class="hover:text-brand-500 transition"><i class="fas fa-fan w-4 text-slate-500"></i> Inverter AC Repair</a></li>
                        <li><a href="customer_dashboard.php?emergency=1" class="text-red-400 font-bold hover:text-red-300 transition"><i class="fas fa-exclamation-circle w-4"></i> Instant SOS Dispatch</a></li>
                    </ul>
                </div>

                <!-- Col 3: 3D CAD & Digital Fabrication -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider text-indigo-400">3D Design & CAD Lab</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="customer_dashboard.php?tab=3d_design" class="hover:text-indigo-400 transition"><i class="fas fa-couch w-4 text-indigo-500"></i> Interior 3D ArchViz</a></li>
                        <li><a href="customer_dashboard.php?tab=3d_design" class="hover:text-indigo-400 transition"><i class="fas fa-shapes w-4 text-indigo-500"></i> 3D Printable STL Models</a></li>
                        <li><a href="customer_dashboard.php?tab=3d_design" class="hover:text-indigo-400 transition"><i class="fas fa-drafting-compass w-4 text-indigo-500"></i> SolidWorks Blueprints</a></li>
                        <li><a href="customer_dashboard.php?tab=3d_design" class="hover:text-indigo-400 transition"><i class="fas fa-cubes w-4 text-indigo-500"></i> Product Prototyping</a></li>
                        <li><a href="register.php?role=provider" class="text-indigo-300 font-semibold hover:text-white transition"><i class="fas fa-laptop-code w-4"></i> Freelance as 3D Artist</a></li>
                    </ul>
                </div>

                <!-- Col 4: National Emergency & Service Zones -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Sri Lanka Hotlines</h4>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="text-slate-300">Ambulance Service</span>
                            <span class="text-red-400 font-mono font-bold">1990</span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="text-slate-300">Sri Lanka Police</span>
                            <span class="text-amber-400 font-mono font-bold">119</span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg bg-slate-800/60 border border-slate-700/50">
                            <span class="text-slate-300">Fire & Rescue</span>
                            <span class="text-rose-400 font-mono font-bold">110</span>
                        </div>
                    </div>
                    <div class="pt-2 text-[11px] text-slate-500">
                        <strong class="text-slate-400">Coverage:</strong> Colombo (01-15), Dehiwala, Nugegoda, Kandy, Galle, Negombo, Gampaha.
                    </div>
                </div>

            </div>

            <!-- Bottom Bar -->
            <div class="pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <span>&copy; <?= date('Y') ?> PickFix (Pvt) Ltd. All Rights Reserved. Made with ❤️ in Sri Lanka.</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="setup_db.php" class="text-slate-400 hover:text-emerald-400 transition flex items-center gap-1 font-mono">
                        <i class="fas fa-database text-[10px]"></i> DB Setup & Health
                    </a>
                    <span>•</span>
                    <a href="login.php" class="hover:text-slate-300 transition">Client Login</a>
                    <span>•</span>
                    <a href="register.php?role=provider" class="hover:text-indigo-400 transition">Join Pro Network</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>

