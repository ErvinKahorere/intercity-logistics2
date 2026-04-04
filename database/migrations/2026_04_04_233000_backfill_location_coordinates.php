<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $coordinates = [
            'Arandis' => [-22.4177, 14.9667, 'Erongo'],
            'Aranos' => [-24.1333, 19.1167, 'Hardap'],
            'Ariamsvlei' => [-28.0667, 19.6333, 'Karas'],
            'Aroab' => [-26.8000, 19.6333, 'Karas'],
            'Bagani' => [-18.1167, 21.6000, 'Kavango East'],
            'Bethanie' => [-26.5000, 17.1500, 'Karas'],
            'Bukalo' => [-17.7833, 24.6333, 'Zambezi'],
            'Divundu' => [-18.1167, 21.5667, 'Kavango East'],
            'Eenhana' => [-17.4667, 16.3333, 'Ohangwena'],
            'Gibeon' => [-25.8833, 18.0000, 'Hardap'],
            'Gobabis' => [-22.4556, 18.9631, 'Omaheke'],
            'Grootfontein' => [-19.5667, 18.1167, 'Otjozondjupa'],
            'Grünau' => [-27.7333, 18.9333, 'Karas'],
            'Henties Bay' => [-22.1160, 14.2845, 'Erongo'],
            'Hoachanas' => [-23.9167, 18.0500, 'Hardap'],
            'Karasburg' => [-28.0167, 18.7500, 'Karas'],
            'Karibib' => [-21.9333, 15.8500, 'Erongo'],
            'Katima Mulilo' => [-17.5000, 24.2667, 'Zambezi'],
            'Keetmanshoop' => [-26.5737, 18.1290, 'Karas'],
            'Khorixas' => [-20.3667, 14.9667, 'Kunene'],
            'Koës' => [-25.8833, 19.1167, 'Karas'],
            'Kombat' => [-19.7333, 17.7333, 'Otjozondjupa'],
            'Kongola' => [-17.8000, 23.3333, 'Zambezi'],
            'Leonardville' => [-23.9667, 18.8667, 'Omaheke'],
            'Linyanti' => [-18.2167, 23.3333, 'Zambezi'],
            'Lüderitz' => [-26.6481, 15.1538, 'Karas'],
            'Maltahöhe' => [-24.8333, 16.9833, 'Hardap'],
            'Mariental' => [-24.6333, 17.9667, 'Hardap'],
            'Mpungu' => [-17.5000, 18.6000, 'Kavango West'],
            'Nkurenkuru' => [-17.6167, 18.6167, 'Kavango West'],
            'Noordoewer' => [-28.6333, 17.6167, 'Karas'],
            'Okahandja' => [-21.9847, 16.9175, 'Otjozondjupa'],
            'Okakarara' => [-20.5833, 17.4333, 'Otjozondjupa'],
            'Okongo' => [-17.4333, 17.4333, 'Ohangwena'],
            'Omaruru' => [-21.4333, 15.9333, 'Erongo'],
            'Ondangwa' => [-17.9117, 15.9526, 'Oshana'],
            'Ongwediva' => [-17.7833, 15.7667, 'Oshana'],
            'Opuwo' => [-18.0667, 13.8333, 'Kunene'],
            'Oranjemund' => [-28.5500, 16.4333, 'Karas'],
            'Oshakati' => [-17.7883, 15.7044, 'Oshana'],
            'Oshigambo' => [-17.6833, 15.6833, 'Oshikoto'],
            'Oshikuku' => [-17.4000, 15.9000, 'Omusati'],
            'Otavi' => [-19.6500, 17.3333, 'Otjozondjupa'],
            'Otjiwarongo' => [-20.4637, 16.6477, 'Otjozondjupa'],
            'Outapi' => [-17.5000, 14.9833, 'Omusati'],
            'Outjo' => [-20.1167, 16.1500, 'Kunene'],
            'Rehoboth' => [-23.3190, 17.0800, 'Hardap'],
            'Ruacana' => [-17.4000, 14.3000, 'Omusati'],
            'Rundu' => [-17.9332, 19.7668, 'Kavango East'],
            'Stampriet' => [-24.3500, 18.4000, 'Hardap'],
            'Swakopmund' => [-22.6784, 14.5266, 'Erongo'],
            'Tses' => [-25.8833, 17.2500, 'Hardap'],
            'Tsumeb' => [-19.2500, 17.7167, 'Oshikoto'],
            'Usakos' => [-22.0000, 15.5833, 'Erongo'],
            'Walvis Bay' => [-22.9576, 14.5053, 'Erongo'],
            'Windhoek' => [-22.5609, 17.0658, 'Khomas'],
        ];

        foreach ($coordinates as $city => [$latitude, $longitude, $region]) {
            DB::table('locations')
                ->where('name', $city)
                ->update([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'region' => $region,
                ]);
        }
    }

    public function down(): void
    {
    }
};
