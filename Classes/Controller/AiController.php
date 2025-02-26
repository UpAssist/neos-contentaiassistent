<?php

namespace UpAssist\Neos\ContentAiAssistent\Controller;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use Neos\Flow\Security\Context;
use UpAssist\Neos\ContentAiAssistent\Service\OpenAiService;

class AiController extends ActionController
{
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var OpenAiService
     */
    protected $openAiService;

    /**
     * @var Context
     * @Flow\Inject
     */
    protected Context $securityContext;

    protected $defaultViewObjectName = JsonView::class;

    /**
     * Generates SEO data for a given node and property.
     *
     * @param NodeInterface $node
     * @param string $propertyName
     * @return string
     * @throws Exception
     */
    public function generateSeoDataAction(NodeInterface $node, string $propertyName): string
    {
        $data = $this->openAiService->generateSeoData($node);
        return json_encode($data, true);
    }
}
