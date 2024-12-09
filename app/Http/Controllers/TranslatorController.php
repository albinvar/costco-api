<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Google Translate API Wrapper and Costco Translator",
 *     description="This is a wrapper API for translating queries using Google Translate and searching Costco products."
 * )
 */
class TranslatorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/translate",
     *     summary="Translate and search Costco products",
     *     description="Translate user queries to English, search Costco, and return results in the user's language.",
     *     operationId="translateAndSearch",
     *     tags={"Translator"},
     *     security={{"bearerAuth": {}}}, 
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query in user's language",
     *         required=true,
     *         @OA\Schema(type="string", example="цахилгаан хөргөгч")
     *     ),
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="User's language code (e.g., 'mn' for Mongolian)",
     *         required=true,
     *         @OA\Schema(type="string", example="mn")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful translation and search results",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="results", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="original_name", type="string", example="Refrigerator"),
     *                     @OA\Property(property="translated_name", type="string", example="Хөргөгч"),
     *                     @OA\Property(property="price", type="number", example=399.99)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Missing or invalid parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Query and language are required.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred. Please try again.")
     *         )
     *     )
     * )
     */
    public function translateAndSearch(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'query' => 'required|string',
            'lang' => 'required|string',
        ]);

        $query = $validated['query'];
        $lang = $validated['lang'];

        $translateApiUrl = config('services.translate.url', env('TRANSLATE_API_URL'));
        $translateApiToken = config('services.translate.token', env('TRANSLATE_API_TOKEN'));
        $costcoApiKey = config('services.costco.api_key', env('COSTCO_API_KEY'));

        $client = new Client();

        try {
            // Step 1: Translate Query to English
            $translatedQuery = $this->translate($client, $translateApiUrl, $translateApiToken, $query, 'en');
            if (!$translatedQuery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to translate query.',
                ], 500);
            }

            // Step 2: Search Costco API
            $costcoResults = $this->searchCostco($client, $costcoApiKey, $translatedQuery);
            if (!$costcoResults) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch results from Costco.',
                ], 500);
            }

            // Step 3: Translate Results Back to User's Language
            $translatedResults = $this->translateResults($client, $translateApiUrl, $translateApiToken, $costcoResults, $lang);

            return response()->json([
                'success' => true,
                'results' => $translatedResults,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in translation and search', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    private function translate($client, $url, $token, $text, $lang)
    {
        $response = $client->post($url, [
            'headers' => ['Authorization' => "Bearer $token"],
            'json' => ['text' => $text, 'lang' => $lang],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['translatedText'] ?? null;
    }

    private function searchCostco($client, $apiKey, $query)
    {
        $response = $client->get("https://search.costco.com/api/apps/www_costco_com/query/www_costco_com_search", [
             'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9,ml;q=0.8,ny;q=0.7,ar;q=0.6,ja;q=0.5,ta;q=0.4',
                'Connection' => 'keep-alive',
                'Content-Type' => 'application/json',
                'DNT' => '1',
                'Origin' => 'https://www.costco.com',
                'Referer' => 'https://www.costco.com/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-site',
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
                'sec-ch-ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
                'sec-ch-ua-mobile' => '?1',
                'sec-ch-ua-platform' => '"Android"',
                'x-api-key' => $apiKey,
            ],
            'query' => [
                'expoption' => 'def',
                'q' => $query,
                'locale' => 'en-US',
                'start' => 0,
                'expand' => 'false',
                'userLocation' => 'WA',
                'loc' => '115-bd,1-wh,1250-3pl,1321-wm,1456-3pl,283-wm,561-wm,725-wm,731-wm,758-wm,759-wm,847_0-cor,847_0-cwt,847_0-edi,847_0-ehs,847_0-membership,847_0-mpt,847_0-spc,847_0-wm,847_1-cwt,847_1-edi,847_d-fis,847_lg_n1f-edi,847_NA-cor,847_NA-pharmacy,847_NA-wm,847_ss_u362-edi,847_wp_r458-edi,951-wm,952-wm,9847-wcs',
                'whloc' => '1-wh',
                'fq' => '{!tag=item_program_eligibility}item_program_eligibility:("ShipIt")',
                'chdcategory' => 'true',
                'chdheader' => 'true',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        dd(collect($data['response']['docs'][0])->toArray());
        return $data['results'] ?? [];
    }

    private function translateResults($client, $url, $token, $results, $lang)
    {
        $translatedResults = [];
        foreach ($results as $result) {
            $translatedName = $this->translate($client, $url, $token, $result['name'], $lang);
            $translatedResults[] = [
                'original_name' => $result['name'],
                'translated_name' => $translatedName ?? $result['name'],
                'price' => $result['price'] ?? null,
            ];
        }
        return $translatedResults;
    }
}
