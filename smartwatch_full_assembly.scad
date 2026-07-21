// ================================================================
// SMARTWATCH PRO — COMPLETE ASSEMBLY
// Original: bottom_case() + top_case()
// NEW:      middle_band() — clips over both assembled halves
// ================================================================
$fn = 120;

// ── Shared dimensions (unchanged from original) ───────────────
wall      = 2.5;
case_h    = 22.0;
case_l    = 70.0;
case_w    = 46.5;
strap_gap = 22.2;

usb_w = 12.0;
usb_h =  5.5;

// ================================================================
// ORIGINAL MODULE 1 — REINFORCED LUGS (unchanged)
// ================================================================
module reinforced_lugs() {
    difference() {
        union() {
            for(y_dir = [-1, 1]) {
                translate([0, y_dir * (strap_gap/2 + 2.5), 0])
                hull() {
                    translate([-4, 0, 0])
                        cube([4, 5, 12], center=true);
                    translate([8, 0, -4])
                        sphere(d=8);
                }
            }
        }
        translate([8, 0, -4])
        rotate([90, 0, 0])
            cylinder(d=2.0, h=case_w + 20, center=true);
        translate([12, 0, 0])
            cube([20, strap_gap, 40], center=true);
    }
}

// ================================================================
// ORIGINAL MODULE 2 — BOTTOM CASE (unchanged)
// ================================================================
module bottom_case() {
    union() {
        difference() {
            hull() {
                translate([-case_l/2+15, 0, 0]) cylinder(d=case_w, h=case_h);
                translate([ case_l/2-15, 0, 0]) cylinder(d=case_w, h=case_h);
            }
            translate([0, 0, wall])
            hull() {
                translate([-case_l/2+15, 0, 0]) cylinder(d=case_w-wall*2, h=case_h);
                translate([ case_l/2-15, 0, 0]) cylinder(d=case_w-wall*2, h=case_h);
            }
            translate([15, case_w/2 - wall - 1, 6])
                cube([usb_w, wall + 5, usb_h], center=true);
            translate([0, 0, -1])
                hull() {
                    cube([16, 16, 0.5], center=true);
                    translate([0,0,wall+2]) cube([12, 12, 0.5], center=true);
                }
            for(i = [-2:2]) {
                translate([-20 + (i*6), -case_w/2 - 2, 10])
                    rotate([45, 0, 0]) cube([1.5, 10, 8]);
            }
        }
        translate([ case_l/2 - 2, 0, case_h/2 + 2]) reinforced_lugs();
        translate([-case_l/2 + 2, 0, case_h/2 + 2]) rotate([0,0,180]) reinforced_lugs();
    }
}

// ================================================================
// ORIGINAL MODULE 3 — TOP CASE / FACEPLATE (unchanged)
// ================================================================
module top_case() {
    difference() {
        hull() {
            translate([-case_l/2+15, 0, 0]) cylinder(d=case_w, h=5);
            translate([ case_l/2-15, 0, 0]) cylinder(d=case_w, h=5);
        }
        translate([0,0,-1]) cylinder(d=33.5, h=10);
        translate([0,0,2.5]) cylinder(d=38.5, h=3.5);
    }
}

// ================================================================
// NEW MODULE — MIDDLE BAND (clip-on, 25mm, open top & bottom)
// ================================================================
// Sits between bottom_case (top rim at z=case_h=22) and
// top_case (bottom rim at z=case_h+band_h=47).
// Snaps over both rims via inward lips. Fully open bore.
// Slide switch cutout: 18mm wide × 5mm tall.
// ================================================================

band_h        = 25.0;   // Exact measured gap

// Snap clip parameters — TUNE IF NEEDED
// If clips are too tight to press on:  raise clip_gap toward 0.5
// If clips feel loose or fall off:     lower clip_gap toward 0.2
clip_gap      = 0.35;   // Clearance between clip lip and case rim
clip_lip      = 1.4;    // How far the lip bites inward over the rim

// Slide switch
sw_w          = 19.0;   // 18mm switch + clearance
sw_h          =  5.5;   // 5mm switch + clearance
sw_x          =  15;    // Lateral offset — same side as USB-C port
                        // Change to 0 to center the switch cutout

// Decorative groove
groove_depth  = 0.55;
groove_w      = 1.1;

// ── Shared hull profile ───────────────────────────────────────
module mhull(h, d_override=0) {
    d = (d_override > 0) ? d_override : case_w;
    hull() {
        translate([-case_l/2+15, 0, 0]) cylinder(d=d, h=h);
        translate([ case_l/2-15, 0, 0]) cylinder(d=d, h=h);
    }
}

module mhull_inner(h, extra=0) {
    // Inner bore = case interior diameter + clip_gap so band slides over
    d = case_w - wall*2 + clip_gap*2 + extra;
    hull() {
        translate([-case_l/2+15, 0, 0]) cylinder(d=d, h=h);
        translate([ case_l/2-15, 0, 0]) cylinder(d=d, h=h);
    }
}

// ── Snap lip ring ─────────────────────────────────────────────
// A washer-shaped ring that bites over a case rim.
// flip=false → clips down over bottom_case top rim (at band z=0)
// flip=true  → clips up   over top_case bottom rim (at band z=band_h)
module snap_lip(flip=false) {
    lip_h = 2.0;   // Height of the retaining lip
    translate([0, 0, flip ? band_h - lip_h : 0])
    difference() {
        mhull(lip_h);                                    // Full outer
        translate([0,0,-0.1])
            mhull_inner(lip_h + 0.2, extra=0);          // Bore with gap
        // Chamfer the leading edge so it guides itself onto the rim
        translate([0, 0, flip ? 0 : lip_h - 0.8])
        difference() {
            mhull(1.0);
            hull() {
                translate([-case_l/2+15, 0, 0])
                    cylinder(d=case_w - clip_lip*2 - 0.4, h=1.0);
                translate([ case_l/2-15, 0, 0])
                    cylinder(d=case_w - clip_lip*2 - 0.4, h=1.0);
            }
        }
    }
}

module middle_band() {
    difference() {
        union() {

            // ── Main shell wall ───────────────────────────────
            difference() {
                mhull(band_h);

                // Open bore — slides over the whole assembly
                translate([0, 0, -1])
                    mhull_inner(band_h + 2);

                // ── Chamfer top rim ───────────────────────────
                translate([0, 0, band_h - 1.0])
                difference() {
                    mhull(1.5);
                    hull() {
                        translate([-case_l/2+15, 0, 0])
                            cylinder(d1=case_w-2.8, d2=case_w+0.2, h=1.5);
                        translate([ case_l/2-15, 0, 0])
                            cylinder(d1=case_w-2.8, d2=case_w+0.2, h=1.5);
                    }
                }

                // ── Chamfer bottom rim ────────────────────────
                translate([0, 0, -0.5])
                difference() {
                    mhull(1.5);
                    hull() {
                        translate([-case_l/2+15, 0, 0])
                            cylinder(d1=case_w+0.2, d2=case_w-2.8, h=1.5);
                        translate([ case_l/2-15, 0, 0])
                            cylinder(d1=case_w+0.2, d2=case_w-2.8, h=1.5);
                    }
                }

                // ── Decorative grooves (1/3 and 2/3 height) ──
                for(gz = [band_h * 0.33, band_h * 0.67]) {
                    translate([0, 0, gz - groove_w/2])
                    difference() {
                        mhull(groove_w);
                        hull() {
                            translate([-case_l/2+15, 0, 0])
                                cylinder(d=case_w - groove_depth*2, h=groove_w);
                            translate([ case_l/2-15, 0, 0])
                                cylinder(d=case_w - groove_depth*2, h=groove_w);
                        }
                    }
                }

                // ── Slide switch cutout ───────────────────────
                // Centered in band height, same face as USB-C
                translate([sw_x, case_w/2 - wall, band_h/2])
                    cube([sw_w, wall + 4, sw_h], center=true);

                // Leading chamfer around switch hole
                translate([sw_x, case_w/2 + 0.5, band_h/2])
                    cube([sw_w + 1.2, 2.0, sw_h + 1.2], center=true);
            }

            // ── Snap lips (added back after bore subtraction) ─
            snap_lip(flip=false);
            snap_lip(flip=true);
        }

        // Final clean bore pass — removes any clip artefacts inside
        translate([0, 0, -2])
            mhull_inner(band_h + 4);
    }
}

// ================================================================
// RENDER — FULL ASSEMBLY
// ================================================================
// Bottom case at z=0
color("DimGray")       bottom_case();

// Middle band sitting right on top of bottom case (z=case_h)
color("SlateGray")
translate([0, 0, case_h]) middle_band();

// Top case sitting on top of band (z=case_h + band_h)
color("DimGray")
translate([0, 0, case_h + band_h]) top_case();


// ================================================================
// TO EXPORT ONLY THE MIDDLE BAND FOR PRINTING:
// Comment out bottom_case() and top_case() renders above,
// then uncomment the line below:
// ================================================================
// middle_band();


// ================================================================
// PRINT SETTINGS FOR MIDDLE BAND:
// • Layer height  : 0.15 mm
// • Walls         : 4 perimeters
// • Infill        : 40% gyroid
// • Orientation   : Upright as rendered — no supports needed
// • Material      : PETG preferred (snap clips need slight flex)
//                   PLA works, reduce clip_gap to 0.25 for tighter fit
// • Clip tuning   : clip_gap 0.35 is a starting point
//                   Too tight → increase to 0.45
//                   Too loose → decrease to 0.25
// ================================================================
