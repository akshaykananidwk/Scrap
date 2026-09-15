<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * India-first location data: all states/UTs with their GST state codes, plus the
 * cities that actually matter for scrap trading (ports, metal hubs, industrial
 * clusters). Admins can import the full city/pincode list via CSV later.
 */
final class LocationSeeder
{
    /** [name, code, gst_state_code, lat, lng] */
    public const STATES = [
        ['Andhra Pradesh', 'AP', '37', 15.9129, 79.7400],
        ['Arunachal Pradesh', 'AR', '12', 28.2180, 94.7278],
        ['Assam', 'AS', '18', 26.2006, 92.9376],
        ['Bihar', 'BR', '10', 25.0961, 85.3131],
        ['Chhattisgarh', 'CG', '22', 21.2787, 81.8661],
        ['Goa', 'GA', '30', 15.2993, 74.1240],
        ['Gujarat', 'GJ', '24', 22.2587, 71.1924],
        ['Haryana', 'HR', '06', 29.0588, 76.0856],
        ['Himachal Pradesh', 'HP', '02', 31.1048, 77.1734],
        ['Jharkhand', 'JH', '20', 23.6102, 85.2799],
        ['Karnataka', 'KA', '29', 15.3173, 75.7139],
        ['Kerala', 'KL', '32', 10.8505, 76.2711],
        ['Madhya Pradesh', 'MP', '23', 22.9734, 78.6569],
        ['Maharashtra', 'MH', '27', 19.7515, 75.7139],
        ['Manipur', 'MN', '14', 24.6637, 93.9063],
        ['Meghalaya', 'ML', '17', 25.4670, 91.3662],
        ['Mizoram', 'MZ', '15', 23.1645, 92.9376],
        ['Nagaland', 'NL', '13', 26.1584, 94.5624],
        ['Odisha', 'OD', '21', 20.9517, 85.0985],
        ['Punjab', 'PB', '03', 31.1471, 75.3412],
        ['Rajasthan', 'RJ', '08', 27.0238, 74.2179],
        ['Sikkim', 'SK', '11', 27.5330, 88.5122],
        ['Tamil Nadu', 'TN', '33', 11.1271, 78.6569],
        ['Telangana', 'TS', '36', 18.1124, 79.0193],
        ['Tripura', 'TR', '16', 23.9408, 91.9882],
        ['Uttar Pradesh', 'UP', '09', 26.8467, 80.9462],
        ['Uttarakhand', 'UK', '05', 30.0668, 79.0193],
        ['West Bengal', 'WB', '19', 22.9868, 87.8550],
        ['Andaman and Nicobar Islands', 'AN', '35', 11.7401, 92.6586],
        ['Chandigarh', 'CH', '04', 30.7333, 76.7794],
        ['Dadra and Nagar Haveli and Daman and Diu', 'DH', '26', 20.3974, 72.8328],
        ['Delhi', 'DL', '07', 28.7041, 77.1025],
        ['Jammu and Kashmir', 'JK', '01', 33.7782, 76.5762],
        ['Ladakh', 'LA', '38', 34.2996, 78.2932],
        ['Lakshadweep', 'LD', '31', 10.5667, 72.6417],
        ['Puducherry', 'PY', '34', 11.9416, 79.8083],
    ];

    /** state code => [[city, lat, lng, is_major], …] */
    public const CITIES = [
        'GJ' => [['Ahmedabad', 23.0225, 72.5714, 1], ['Surat', 21.1702, 72.8311, 1], ['Rajkot', 22.3039, 70.8022, 1], ['Jamnagar', 22.4707, 70.0577, 1], ['Bhavnagar', 21.7645, 72.1519, 1], ['Alang', 21.4000, 72.1900, 1], ['Vadodara', 22.3072, 73.1812, 1], ['Gandhidham', 23.0800, 70.1300, 1], ['Morbi', 22.8173, 70.8370, 0], ['Anand', 22.5645, 72.9289, 0]],
        'MH' => [['Mumbai', 19.0760, 72.8777, 1], ['Pune', 18.5204, 73.8567, 1], ['Nagpur', 21.1458, 79.0882, 1], ['Nashik', 19.9975, 73.7898, 1], ['Aurangabad', 19.8762, 75.3433, 0], ['Thane', 19.2183, 72.9781, 1], ['Kolhapur', 16.7050, 74.2433, 0], ['Taloja', 19.0800, 73.1000, 0]],
        'DL' => [['New Delhi', 28.6139, 77.2090, 1], ['Mayapuri', 28.6270, 77.1250, 1], ['Narela', 28.8560, 77.0920, 0], ['Wazirpur', 28.6970, 77.1660, 0]],
        'TN' => [['Chennai', 13.0827, 80.2707, 1], ['Coimbatore', 11.0168, 76.9558, 1], ['Madurai', 9.9252, 78.1198, 0], ['Tiruchirappalli', 10.7905, 78.7047, 0], ['Salem', 11.6643, 78.1460, 1], ['Tuticorin', 8.7642, 78.1348, 1]],
        'KA' => [['Bengaluru', 12.9716, 77.5946, 1], ['Mysuru', 12.2958, 76.6394, 0], ['Hubballi', 15.3647, 75.1240, 0], ['Mangaluru', 12.9141, 74.8560, 1], ['Belagavi', 15.8497, 74.4977, 0]],
        'WB' => [['Kolkata', 22.5726, 88.3639, 1], ['Howrah', 22.5958, 88.2636, 1], ['Durgapur', 23.5204, 87.3119, 1], ['Asansol', 23.6739, 86.9524, 0], ['Haldia', 22.0667, 88.0698, 1]],
        'UP' => [['Kanpur', 26.4499, 80.3319, 1], ['Ghaziabad', 28.6692, 77.4538, 1], ['Noida', 28.5355, 77.3910, 1], ['Lucknow', 26.8467, 80.9462, 1], ['Agra', 27.1767, 78.0081, 0], ['Meerut', 28.9845, 77.7064, 0], ['Muradnagar', 28.7800, 77.4900, 0]],
        'HR' => [['Gurugram', 28.4595, 77.0266, 1], ['Faridabad', 28.4089, 77.3178, 1], ['Rohtak', 28.8955, 76.6066, 0], ['Panipat', 29.3909, 76.9635, 1], ['Hisar', 29.1492, 75.7217, 0]],
        'PB' => [['Ludhiana', 30.9010, 75.8573, 1], ['Amritsar', 31.6340, 74.8723, 0], ['Jalandhar', 31.3260, 75.5762, 1], ['Mandi Gobindgarh', 30.6700, 76.3000, 1]],
        'RJ' => [['Jaipur', 26.9124, 75.7873, 1], ['Jodhpur', 26.2389, 73.0243, 0], ['Bhilwara', 25.3407, 74.6313, 0], ['Alwar', 27.5530, 76.6346, 0], ['Bhiwadi', 28.2100, 76.8600, 1]],
        'TS' => [['Hyderabad', 17.3850, 78.4867, 1], ['Warangal', 17.9689, 79.5941, 0], ['Nizamabad', 18.6725, 78.0941, 0]],
        'AP' => [['Visakhapatnam', 17.6868, 83.2185, 1], ['Vijayawada', 16.5062, 80.6480, 0], ['Guntur', 16.3067, 80.4365, 0]],
        'MP' => [['Indore', 22.7196, 75.8577, 1], ['Bhopal', 23.2599, 77.4126, 0], ['Jabalpur', 23.1815, 79.9864, 0], ['Pithampur', 22.6100, 75.6900, 1]],
        'CG' => [['Raipur', 21.2514, 81.6296, 1], ['Bhilai', 21.1938, 81.3509, 1], ['Bilaspur', 22.0797, 82.1409, 0]],
        'JH' => [['Jamshedpur', 22.8046, 86.2029, 1], ['Ranchi', 23.3441, 85.3096, 0], ['Bokaro', 23.6693, 86.1511, 1]],
        'OD' => [['Bhubaneswar', 20.2961, 85.8245, 0], ['Rourkela', 22.2604, 84.8536, 1], ['Cuttack', 20.4625, 85.8830, 0], ['Paradip', 20.3167, 86.6100, 1]],
        'KL' => [['Kochi', 9.9312, 76.2673, 1], ['Thiruvananthapuram', 8.5241, 76.9366, 0], ['Kozhikode', 11.2588, 75.7804, 0]],
        'BR' => [['Patna', 25.5941, 85.1376, 0], ['Muzaffarpur', 26.1209, 85.3647, 0]],
        'AS' => [['Guwahati', 26.1445, 91.7362, 0]],
        'GA' => [['Vasco da Gama', 15.3860, 73.8157, 0], ['Panaji', 15.4909, 73.8278, 0]],
        'UK' => [['Haridwar', 29.9457, 78.1642, 0], ['Rudrapur', 28.9800, 79.4000, 0]],
        'HP' => [['Baddi', 30.9578, 76.7914, 0], ['Shimla', 31.1048, 77.1734, 0]],
        'CH' => [['Chandigarh', 30.7333, 76.7794, 0]],
        'JK' => [['Jammu', 32.7266, 74.8570, 0], ['Srinagar', 34.0837, 74.7973, 0]],
        'PY' => [['Puducherry', 11.9416, 79.8083, 0]],
    ];

    public static function run(Database $db): void
    {
        $countryId = (int) $db->scalar('SELECT id FROM countries WHERE iso2 = :i', ['i' => 'IN'], 0);
        if ($countryId === 0) {
            $countryId = $db->insert('countries', [
                'name' => 'India',
                'iso2' => 'IN',
                'iso3' => 'IND',
                'phone_code' => '91',
                'currency' => 'INR',
                'is_active' => 1,
                'created_at' => now(),
            ]);
        }

        $stateIds = [];
        foreach (self::STATES as [$name, $code, $gstCode, $lat, $lng]) {
            $slug = slugify($name);
            $existing = $db->first('SELECT id FROM states WHERE slug = :s', ['s' => $slug]);
            if ($existing !== null) {
                $stateIds[$code] = (int) $existing['id'];
                continue;
            }
            $stateIds[$code] = $db->insert('states', [
                'country_id' => $countryId,
                'name' => $name,
                'slug' => $slug,
                'code' => $code,
                'gst_state_code' => $gstCode,
                'latitude' => $lat,
                'longitude' => $lng,
                'is_active' => 1,
                'created_at' => now(),
            ]);
        }

        foreach (self::CITIES as $stateCode => $cities) {
            $stateId = $stateIds[$stateCode] ?? null;
            if ($stateId === null) {
                continue;
            }
            foreach ($cities as [$cityName, $lat, $lng, $isMajor]) {
                $slug = slugify($cityName . '-' . $stateCode);
                if ($db->first('SELECT id FROM cities WHERE slug = :s', ['s' => $slug]) !== null) {
                    continue;
                }
                $db->insert('cities', [
                    'state_id' => $stateId,
                    'name' => $cityName,
                    'slug' => $slug,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_major' => $isMajor,
                    'is_active' => 1,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
