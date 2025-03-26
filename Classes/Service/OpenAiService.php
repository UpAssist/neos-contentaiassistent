<?php

namespace UpAssist\Neos\ContentAiAssistent\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\Exception;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use OpenAI\Client;

/**
 * @Flow\Scope("singleton")
 */
class OpenAiService
{
    /**
     * @Flow\InjectConfiguration(path="aiModel")
     * @var string
     */
    protected $aiModel;

    /**
     * @Flow\InjectConfiguration(path="apiKey")
     * @var string
     */
    protected $apiKey;

    /**
     * @Flow\InjectConfiguration(path="textProperties")
     * @var string
     */
    protected $textProperties;

    /**
     * @var Client
     */
    protected Client $client;

    /**
     * @throws Exception
     */
    public function generateSeoData(NodeInterface $node): array
    {
        $response = \OpenAI::client($this->apiKey)->chat()->create([
            'model' => $this->aiModel,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO content assistant.'],
                ['role' => 'system', 'content' => 'Generate a JSON object containing the following:
            {
                "summary": "...",
                "metaKeywords": "...",
                "metaDescription": "..."
            }'],
                ['role' => 'system', 'content' => 'You use the language matching the language of the provided context.'],
                ['role' => 'system', 'content' => 'Make sure the character counts include whitespace.'],
                ['role' => 'system', 'content' => 'Truncate the text if it exceeds the limit.'],
                ['role' => 'system', 'content' => 'Ensure the output is a well-formed JSON object. Do not wrap it in a codeblock. Return it as plain text.'],
                ['role' => 'system', 'content' => 'Use these character limits:
            - summary: max 200 characters
            - metaKeywords: max 255 characters
            - metaDescription: max 160 characters
        '],
                ['role' => 'system', 'content' => 'If any text exceeds its character limit, truncate it at the last complete word that fits within the limit.'],
                ['role' => 'user', 'content' => $this->extractPageText($node)]
            ]
        ]);

        $content = $response['choices'][0]['message']['content'];
        $data = json_decode($content, true);

        // Check for valid JSON and return the result array
        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                'summary' => $this->truncateText($data['summary'], 200) ?? '',
                'metaKeywords' => $this->truncateText($data['metaKeywords'], 255) ?? '',
                'metaDescription' => $this->truncateText($data['metaDescription'], 160) ?? ''
            ];
        } else {
            // Handle parsing error gracefully
            return [
                'summary' => '',
                'metaKeywords' => '',
                'metaDescription' => ''
            ];
        }
    }

    /**
     * @param NodeInterface $node
     * @return string
     * @throws \Neos\Eel\Exception
     */
    protected function extractPageText(NodeInterface $node): string
    {
        $text = [];

        // Load settings for text properties
        $textProperties = $this->textProperties ?? ['text'];

        // Initialize FlowQuery and find all Neos.Neos:Content nodes
        $query = new FlowQuery([$node]);
        $contentNodes = $query->find('[instanceof Neos.Neos:Content]')->get();

        foreach ($contentNodes as $contentNode) {
            // Loop through all specified properties
            foreach ($textProperties as $property) {
                if ($contentNode->hasProperty($property)) {
                    $text[] = $contentNode->getProperty($property);
                }
            }
        }

        // Return the concatenated and sanitized text
        return strip_tags(implode(' ', $text));
    }

    /**
     * @param string $text
     * @param int $maxLength
     * @return mixed|string
     */
    function truncateText(string $text, int $maxLength = 255)
    {
        // Remove extra spaces and strip HTML tags
        $text = trim(strip_tags($text));

        // If text is already within the limit, return it
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        // Truncate text at the max length
        $truncated = substr($text, 0, $maxLength);

        // Ensure the last word is not cut off
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        // Remove trailing commas or other unwanted characters
        return rtrim($truncated, ',;:.!?');

    }

}
