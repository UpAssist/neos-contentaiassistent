<?php
namespace UpAssist\Neos\ContentAiAssistent\Command;

use Neos\ContentRepository\Domain\Factory\NodeFactory;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Eel\Exception;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use UpAssist\Neos\ContentAiAssistent\Service\OpenAiService;

class AiCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var OpenAiService
     */
    protected OpenAiService $aiService;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected ContextFactoryInterface $contextFactory;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected NodeTypeManager $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected NodeDataRepository $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected NodeFactory $nodeFactory;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected PersistenceManagerInterface $persistenceManager;

    /**
     * Updates the SEO data for a specific node or for all nodes of a given type.
     *
     * @param string|null $nodeIdentifier The identifier of the node to update. Optional.
     * @param string|null $nodeType The node type to update SEO data for. Optional.
     * @return void
     */
    public function updateSeoDataCommand(string $nodeIdentifier = null, string $nodeType = null): void
    {
        if ($nodeIdentifier !== null) {
            /** @var NodeData $nodeData */
            $nodeData = $this->nodeDataRepository->findByNodeIdentifier($nodeIdentifier) ? $this->nodeDataRepository->findByNodeIdentifier($nodeIdentifier)[0] : null;
            /** @var NodeInterface $node */
            $node = $this->getNodeInterfaceFromNodeData($nodeData);
            if (empty($node->getProperty('metaKeywords')) || empty($node->getProperty('metaDescription'))) {
                $this->updateSeoData($node);
            } else {
                $this->outputLine('The node ' . $node->getProperty('title') . ' has SEO data set.');
            }
        } elseif ($nodeType !== null) {
            $this->updateSeoDataByType($nodeType);
        } else {
            $this->outputLine('Please specify either a nodeIdentifier or a nodeType.');
        }
    }

    /**
     * @param string $nodeType
     * @return void
     * @throws NodeConfigurationException
     * @throws NodeException
     * @throws \Neos\Eel\Exception
     */
    public function updateSeoDataByType(string $nodeType): void
    {
        $possibleDimensions = (new ContentDimensionCombinator())->getAllAllowedCombinations();
        foreach ($possibleDimensions as $dimensions) {
            // Create a context for the workspace
            $context = $this->contextFactory->create([
                'workspaceName' => 'live',
                'invisibleContentShown' => true,
                'removedContentShown' => false,
                'inaccessibleContentShown' => false,
                'dimensions' => $dimensions
            ]);

            /** @var NodeInterface $siteNode */
            $siteNode = $context->getCurrentSiteNode();

            if (empty($siteNode)) {
                $this->outputLine('<warning>The site node does not exist.</warning>');
                $this->sendAndExit();
            }

            $query = new FlowQuery([$siteNode]);
            $nodes = $query->find('[instanceof ' . $nodeType . ']')->get();

            if (empty($nodes)) {
                $this->outputLine('<warning>Their are no nodes found with the type: ' . $nodeType . '.</warning>');
                $this->sendAndExit();
            }

            /** @var NodeInterface $node */
            foreach ($nodes as $node) {
                if (empty($node->getProperty('metaKeywords')) || empty($node->getProperty('metaDescription'))) {
                    $this->updateSeoData($node);
                } else {
                    $this->outputLine('<info>The node ' . $node->getProperty('title') . ' has SEO data set.</info>');
                }
            }
        }
    }

    /**
     * @param NodeInterface $node
     * @return void
     * @throws NodeException
     * @throws Exception
     */
    protected function updateSeoData(NodeInterface $node): void
    {
        $data = $this->aiService->generateSeoData($node);

        if (!empty($data)) {
            $node->setProperty('metaKeywords', ($node->getProperty('keywords') ? $node->getProperty('keywords') : $data['metaKeywords']));
            $node->setProperty('metaDescription', ($node->getProperty('metaDescription') ? $node->getProperty('metaDescription') : $data['metaDescription']));
            $this->outputLine('<info>Updated SEO data for: "' . $node->getProperty('title') . '"</info>');
            $this->persistenceManager->persistAll();
        }
    }

    /**
     * @param NodeData $nodeData
     * @return NodeInterface|null
     * @throws \Neos\ContentRepository\Exception\NodeConfigurationException
     */
    protected function getNodeInterfaceFromNodeData(NodeData $nodeData): ?NodeInterface
    {
        // Create a context for the workspace
        $context = $this->contextFactory->create([
            'workspaceName' => $nodeData->getWorkspace()->getName(),
            'invisibleContentShown' => true,
            'removedContentShown' => false,
            'inaccessibleContentShown' => false
        ]);

        // Create a NodeInterface from NodeData
        return $this->nodeFactory->createFromNodeData($nodeData, $context);
    }
}
