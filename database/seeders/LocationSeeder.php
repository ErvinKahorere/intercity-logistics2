<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $locations = [
            'Windhoek',
            'Walvis Bay',
            'Swakopmund',
            'Henties Bay',
            'Arandis',

            'Gobabis',
            'Okahandja',
            'Rehoboth',
            'Oshakati',
            'Ongwediva',
            'Ondangwa',
            'Outapi',
            'Opuwo',
            'Khorixas',

            'Rundu',
            'Nkurenkuru',
            'Katima Mulilo',
            'Linyanti',
            'Bukalo',

            'Mariental',
            'Maltahöhe',
            'Gibeon',
            'Aranos',

            'Keetmanshoop',
            'Lüderitz',
            'Oranjemund',
            'Karasburg',
            'Bethanie',
            'Tses',
            'Ariamsvlei',

            'Tsumeb',
            'Grootfontein',
            'Otavi',
            'Kombat',

            'Outjo',
            'Otjiwarongo',
            'Okakarara',

            'Usakos',
            'Karibib',
            'Omaruru',

            'Oshikuku',
            'Eenhana',
            'Okongo',
            'Oshigambo',
            'Ruacana',

            'Divundu',
            'Bagani',
            'Kongola',
            'Mpungu',

            'Hoachanas',
            'Leonardville',
            'Stampriet',
            'Aroab',
            'Koës',
            'Grünau',
            'Noordoewer',
        ];


        foreach ($locations as $loc) {
            Location::create([
                'name' => $loc
            ]);
        }
    }

}
