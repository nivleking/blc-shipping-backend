<?php

namespace App\Utilities;

trait UtilitiesHelper
{
    protected function getUnrolledPenalties()
    {
        return [
            // Committed dry containers
            'dry_committed' => 5_000_000,
            // Non-committed dry containers
            'dry_non_committed' => 2_000_000,
            // Committed reefer containers
            'reefer_committed' => 6_000_000,
            // Non-committed reefer containers
            'reefer_non_committed' => 8_000_000
        ];
    }

    protected function generateContainerColor($destination)
    {
        $colorMap = [
            'SBY' => 'red',
            'MDN' => 'green',
            'MKS' => 'blue',
            'JYP' => 'yellow',
            'BPN' => 'black',
            'BKS' => 'orange',
            'BGR' => 'pink',
            'BTH' => 'brown',
            'AMQ' => 'cyan',
            'SMR' => 'teal'
        ];

        $destCode = substr($destination, 0, 3);
        return $colorMap[$destCode] ?? 'gray';
    }

    protected function getPorts($portsCount)
    {
        $ports = [
            2 => ["SBY", "MDN"],
            3 => ["SBY", "MDN", "MKS"],
            4 => ["SBY", "MDN", "MKS", "JYP"],
            5 => ["SBY", "MDN", "MKS", "JYP", "BPN"],
            6 => ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS"],
            7 => ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS", "BGR"],
            8 => ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS", "BGR", "BTH"],
            9 => ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ"],
            10 => ["SBY", "MDN", "MKS", "JYP", "BPN", "BKS", "BGR", "BTH", "AMQ", "SMR"],
        ];

        return $ports[$portsCount] ?? [];
    }

    protected function getBasePriceMap()
    {
        return [
            // SBY routes (SURABAYA)
            "SBY-MDN-Reefer" => 23000000,
            "SBY-MDN-Dry" => 14000000,
            "SBY-MKS-Reefer" => 27600000,
            "SBY-MKS-Dry" => 16800000,
            "SBY-JYP-Reefer" => 32200000,
            "SBY-JYP-Dry" => 19600000,
            "SBY-BPN-Reefer" => 36800000,
            "SBY-BPN-Dry" => 22400000,
            "SBY-BKS-Reefer" => 26000000,
            "SBY-BKS-Dry" => 15000000,
            "SBY-BGR-Reefer" => 25000000,
            "SBY-BGR-Dry" => 14000000,
            "SBY-BTH-Reefer" => 27000000,
            "SBY-BTH-Dry" => 16000000,
            "SBY-AMQ-Reefer" => 32000000,
            "SBY-AMQ-Dry" => 19000000,
            "SBY-SMR-Reefer" => 29000000,
            "SBY-SMR-Dry" => 17000000,

            // MDN routes (MEDAN)
            "MDN-MKS-Reefer" => 23000000,
            "MDN-MKS-Dry" => 14000000,
            "MDN-JYP-Reefer" => 27600000,
            "MDN-JYP-Dry" => 16800000,
            "MDN-BPN-Reefer" => 32200000,
            "MDN-BPN-Dry" => 19600000,
            "MDN-SBY-Reefer" => 36800000,
            "MDN-SBY-Dry" => 22400000,
            "MDN-BKS-Reefer" => 25000000,
            "MDN-BKS-Dry" => 14000000,
            "MDN-BGR-Reefer" => 24000000,
            "MDN-BGR-Dry" => 13000000,
            "MDN-BTH-Reefer" => 23000000,
            "MDN-BTH-Dry" => 12000000,
            "MDN-AMQ-Reefer" => 30000000,
            "MDN-AMQ-Dry" => 18000000,
            "MDN-SMR-Reefer" => 28000000,
            "MDN-SMR-Dry" => 16000000,

            // MKS routes (MAKASSAR)
            "MKS-JYP-Reefer" => 23000000,
            "MKS-JYP-Dry" => 14000000,
            "MKS-BPN-Reefer" => 27600000,
            "MKS-BPN-Dry" => 16800000,
            "MKS-SBY-Reefer" => 32200000,
            "MKS-SBY-Dry" => 19600000,
            "MKS-MDN-Reefer" => 36800000,
            "MKS-MDN-Dry" => 22400000,
            "MKS-BKS-Reefer" => 23000000,
            "MKS-BKS-Dry" => 13000000,
            "MKS-BGR-Reefer" => 22000000,
            "MKS-BGR-Dry" => 12000000,
            "MKS-BTH-Reefer" => 26000000,
            "MKS-BTH-Dry" => 15000000,
            "MKS-AMQ-Reefer" => 28000000,
            "MKS-AMQ-Dry" => 17000000,
            "MKS-SMR-Reefer" => 27000000,
            "MKS-SMR-Dry" => 16000000,

            // JYP routes (JAYAPURA)
            "JYP-BPN-Reefer" => 23000000,
            "JYP-BPN-Dry" => 14000000,
            "JYP-SBY-Reefer" => 27600000,
            "JYP-SBY-Dry" => 16800000,
            "JYP-MDN-Reefer" => 32200000,
            "JYP-MDN-Dry" => 19600000,
            "JYP-MKS-Reefer" => 36800000,
            "JYP-MKS-Dry" => 22400000,
            "JYP-BKS-Reefer" => 22000000,
            "JYP-BKS-Dry" => 12000000,
            "JYP-BGR-Reefer" => 21000000,
            "JYP-BGR-Dry" => 11000000,
            "JYP-BTH-Reefer" => 25000000,
            "JYP-BTH-Dry" => 14000000,
            "JYP-AMQ-Reefer" => 29000000,
            "JYP-AMQ-Dry" => 18000000,
            "JYP-SMR-Reefer" => 26000000,
            "JYP-SMR-Dry" => 15000000,

            // BPN routes (BALIKPAPAN)
            "BPN-JYP-Reefer" => 23000000,
            "BPN-JYP-Dry" => 14000000,
            "BPN-SBY-Reefer" => 27600000,
            "BPN-SBY-Dry" => 16800000,
            "BPN-MDN-Reefer" => 32200000,
            "BPN-MDN-Dry" => 19600000,
            "BPN-MKS-Reefer" => 36800000,
            "BPN-MKS-Dry" => 22400000,
            "BPN-BKS-Reefer" => 23000000,
            "BPN-BKS-Dry" => 13000000,
            "BPN-BGR-Reefer" => 22000000,
            "BPN-BGR-Dry" => 12000000,
            "BPN-BTH-Reefer" => 25000000,
            "BPN-BTH-Dry" => 15000000,
            "BPN-AMQ-Reefer" => 28000000,
            "BPN-AMQ-Dry" => 17000000,
            "BPN-SMR-Reefer" => 24000000,
            "BPN-SMR-Dry" => 14000000,

            // BKS routes
            "BKS-SBY-Reefer" => 21000000,
            "BKS-SBY-Dry" => 12000000,
            "BKS-MKS-Reefer" => 23000000,
            "BKS-MKS-Dry" => 13000000,
            "BKS-MDN-Reefer" => 25000000,
            "BKS-MDN-Dry" => 15000000,
            "BKS-JYP-Reefer" => 22000000,
            "BKS-JYP-Dry" => 12000000,
            "BKS-BPN-Reefer" => 24000000,
            "BKS-BPN-Dry" => 14000000,
            "BKS-BGR-Reefer" => 20000000,
            "BKS-BGR-Dry" => 11000000,
            "BKS-BTH-Reefer" => 26000000,
            "BKS-BTH-Dry" => 16000000,
            "BKS-AMQ-Reefer" => 29000000,
            "BKS-AMQ-Dry" => 18000000,
            "BKS-SMR-Reefer" => 25000000,
            "BKS-SMR-Dry" => 15000000,

            // BGR routes
            "BGR-SBY-Reefer" => 22000000,
            "BGR-SBY-Dry" => 13000000,
            "BGR-MKS-Reefer" => 24000000,
            "BGR-MKS-Dry" => 14000000,
            "BGR-MDN-Reefer" => 26000000,
            "BGR-MDN-Dry" => 16000000,
            "BGR-JYP-Reefer" => 23000000,
            "BGR-JYP-Dry" => 13000000,
            "BGR-BPN-Reefer" => 25000000,
            "BGR-BPN-Dry" => 15000000,
            "BGR-BKS-Reefer" => 21000000,
            "BGR-BKS-Dry" => 12000000,
            "BGR-BTH-Reefer" => 27000000,
            "BGR-BTH-Dry" => 17000000,
            "BGR-AMQ-Reefer" => 30000000,
            "BGR-AMQ-Dry" => 19000000,
            "BGR-SMR-Reefer" => 26000000,
            "BGR-SMR-Dry" => 16000000,

            // BTH routes
            "BTH-SBY-Reefer" => 23000000,
            "BTH-SBY-Dry" => 14000000,
            "BTH-MKS-Reefer" => 25000000,
            "BTH-MKS-Dry" => 15000000,
            "BTH-MDN-Reefer" => 27000000,
            "BTH-MDN-Dry" => 17000000,
            "BTH-JYP-Reefer" => 24000000,
            "BTH-JYP-Dry" => 14000000,
            "BTH-BPN-Reefer" => 26000000,
            "BTH-BPN-Dry" => 16000000,
            "BTH-BKS-Reefer" => 22000000,
            "BTH-BKS-Dry" => 13000000,
            "BTH-BGR-Reefer" => 23000000,
            "BTH-BGR-Dry" => 14000000,
            "BTH-AMQ-Reefer" => 31000000,
            "BTH-AMQ-Dry" => 20000000,
            "BTH-SMR-Reefer" => 27000000,
            "BTH-SMR-Dry" => 17000000,

            // AMQ routes
            "AMQ-SBY-Reefer" => 24000000,
            "AMQ-SBY-Dry" => 15000000,
            "AMQ-MKS-Reefer" => 26000000,
            "AMQ-MKS-Dry" => 16000000,
            "AMQ-MDN-Reefer" => 28000000,
            "AMQ-MDN-Dry" => 18000000,
            "AMQ-JYP-Reefer" => 25000000,
            "AMQ-JYP-Dry" => 15000000,
            "AMQ-BPN-Reefer" => 27000000,
            "AMQ-BPN-Dry" => 17000000,
            "AMQ-BKS-Reefer" => 23000000,
            "AMQ-BKS-Dry" => 14000000,
            "AMQ-BGR-Reefer" => 24000000,
            "AMQ-BGR-Dry" => 15000000,
            "AMQ-BTH-Reefer" => 28000000,
            "AMQ-BTH-Dry" => 18000000,
            "AMQ-SMR-Reefer" => 25000000,
            "AMQ-SMR-Dry" => 15000000,

            // SMR routes
            "SMR-SBY-Reefer" => 25000000,
            "SMR-SBY-Dry" => 16000000,
            "SMR-MKS-Reefer" => 27000000,
            "SMR-MKS-Dry" => 17000000,
            "SMR-MDN-Reefer" => 29000000,
            "SMR-MDN-Dry" => 19000000,
            "SMR-JYP-Reefer" => 26000000,
            "SMR-JYP-Dry" => 16000000,
            "SMR-BPN-Reefer" => 28000000,
            "SMR-BPN-Dry" => 18000000,
            "SMR-BKS-Reefer" => 24000000,
            "SMR-BKS-Dry" => 15000000,
            "SMR-BGR-Reefer" => 25000000,
            "SMR-BGR-Dry" => 16000000,
            "SMR-BTH-Reefer" => 29000000,
            "SMR-BTH-Dry" => 19000000,
            "SMR-AMQ-Reefer" => 26000000,
            "SMR-AMQ-Dry" => 16000000,
        ];
    }
}
