<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;

class AIService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        $apiKey      = config('services.openai.key');
        $this->model = config('services.openai.model', 'gpt-3.5-turbo');

        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Generate a personalised cold outreach email body for the given lead.
     * SerpAPI is used first to research the company so the email contains
     * real, specific details rather than generic filler text.
     *
     * @throws \RuntimeException
     */
    public function generateOutreachEmail(
        Lead $lead,
        string $senderName,
        string $senderCompany
    ): string {
        // 1. Research the company with SerpAPI
        $companyContext = $this->researchCompany($lead);

        // 2. Build a richer prompt using the research
        $prompt = $this->buildPrompt($lead, $senderName, $senderCompany, $companyContext);

        try {
            $response = $this->client->chat()->create([
                'model'       => $this->model,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an expert B2B copywriter specialising in cold email outreach. '
                            . 'You write concise, personalised, and professional emails that feel human — not spammy. '
                            . 'Use the company research provided to make the email specific and relevant. '
                            . 'Return ONLY the email body (no subject line, no headers). '
                            . 'Keep it under 150 words.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens'  => 400,
                'temperature' => 0.7,
            ]);

            return trim($response->choices[0]->message->content ?? '');

        } catch (\Exception $e) {
            Log::error('AIService: OpenAI call failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to generate email: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate a subject line for the outreach email.
     * SerpAPI research is included so the subject can reference something specific.
     */
    public function generateSubjectLine(Lead $lead, string $senderCompany): string
    {
        $companyContext = $this->researchCompany($lead);

        try {
            $context = $companyContext
                ? "Company: {$lead->company_name}. What they do: {$companyContext}. Sender company: {$senderCompany}."
                : "Company: {$lead->company_name}. Sender company: {$senderCompany}.";

            $response = $this->client->chat()->create([
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Generate a short, personalised cold email subject line (max 10 words). '
                            . 'Make it curiosity-driven, specific to the company, and avoid spam trigger words. '
                            . 'Return only the subject line text, nothing else.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $context,
                    ],
                ],
                'max_tokens'  => 60,
                'temperature' => 0.8,
            ]);

            return trim($response->choices[0]->message->content ?? "Quick question for {$lead->company_name}");

        } catch (\Exception $e) {
            Log::warning('AIService: Subject generation failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);

            return "Quick question for {$lead->company_name}";
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SerpAPI — Company Research
    // ─────────────────────────────────────────────────────────────

    /**
     * Use SerpAPI to search for the company and extract a short description
     * from the organic result snippets. Returns an empty string if the API key
     * is not set or the search returns nothing useful.
     */
    private function researchCompany(Lead $lead): string
    {
        $apiKey = config('services.serpapi.key');

        if (empty($apiKey)) {
            Log::debug('AIService: SERPAPI_KEY not set — skipping company research');
            return '';
        }

        try {
            $query    = $lead->company_name . ' ' . parse_url($lead->website, PHP_URL_HOST);

            $response = Http::timeout(10)->get('https://serpapi.com/search', [
                'engine'  => 'google',
                'q'       => $query,
                'api_key' => $apiKey,
                'num'     => 3,
                'hl'      => 'en',
                'gl'      => 'us',
                'output'  => 'json',
            ]);

            if (! $response->successful()) {
                Log::debug('AIService[SerpAPI] research HTTP ' . $response->status());
                return '';
            }

            $data    = $response->json();
            $context = [];

            // Knowledge graph gives the best one-line company description
            $kg = $data['knowledge_graph'] ?? [];
            if (! empty($kg['description'])) {
                $context[] = $kg['description'];
            }
            if (! empty($kg['type'])) {
                $context[] = 'Industry: ' . $kg['type'];
            }

            // Organic snippets — first 2 results
            foreach (array_slice($data['organic_results'] ?? [], 0, 2) as $result) {
                if (! empty($result['snippet'])) {
                    $context[] = $result['snippet'];
                }
            }

            // Answer box (sometimes appears for branded queries)
            $answerBox = $data['answer_box'] ?? [];
            if (! empty($answerBox['snippet'])) {
                $context[] = $answerBox['snippet'];
            }

            $summary = implode(' | ', array_filter($context));

            Log::debug('AIService[SerpAPI] company research', [
                'lead_id' => $lead->id,
                'summary' => substr($summary, 0, 200),
            ]);

            return $summary;

        } catch (\Throwable $e) {
            Log::warning('AIService[SerpAPI] research failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
            return '';
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Prompt Builder
    // ─────────────────────────────────────────────────────────────

    /**
     * Build the OpenAI prompt, injecting SerpAPI research when available.
     */
    private function buildPrompt(
        Lead $lead,
        string $senderName,
        string $senderCompany,
        string $companyContext = ''
    ): string {
        $researchBlock = $companyContext
            ? "\n\nCompany research (use this to personalise the email):\n{$companyContext}"
            : '';

        return "Write a personalised cold outreach email to a decision-maker at \"{$lead->company_name}\" "
            . "(website: {$lead->website}).{$researchBlock}\n\n"
            . "The email is from {$senderName} at {$senderCompany}. "
            . "Mention their company by name and reference something specific from the research above. "
            . "The goal is to start a conversation and offer value — do NOT be pushy or salesy. "
            . "End with a soft call to action (e.g., a short call or quick reply). "
            . "Sign off with {$senderName} from {$senderCompany}.";
    }
}
