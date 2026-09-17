<?php

use App\Support\PerhitunganSksBlok;

require dirname(__DIR__).'/vendor/autoload.php';

assert(PerhitunganSksBlok::keSkala('1.25') === 12500);
assert(PerhitunganSksBlok::keSkala('0.0001') === 1);
assert(PerhitunganSksBlok::keSkala('1.23456') === 0);
assert(PerhitunganSksBlok::keSkala('1.2500') + PerhitunganSksBlok::keSkala('0.7500') === PerhitunganSksBlok::keSkala('2.0'));
assert(PerhitunganSksBlok::bobotPertemuan(3.0, 4) === 0.75);
assert(PerhitunganSksBlok::bobotDosen(0.75, 2) === 0.375);
assert(PerhitunganSksBlok::bobotPertemuan(3.0, 0) === 0.0);

echo "Check SKS Blok berhasil.\n";
