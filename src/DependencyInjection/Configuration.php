<?php

declare(strict_types=1);

namespace GoetasWebservices\SoapServices\SoapServer\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('soap_server');
        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            $rootNode = $treeBuilder->root('soap_server');
        }
        $rootNode
            ->children()
            ->scalarNode('naming_strategy')
            ->defaultValue('short')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('path_generator')
            ->defaultValue('psr4')
            ->cannotBeEmpty()
            ->end()
            ->arrayNode('namespaces')->fixXmlConfig('namespace')
            ->cannotBeEmpty()->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('scalar')
            ->end()
            ->end()
            ->arrayNode('known_locations')->fixXmlConfig('known_location')
            ->prototype('scalar')
            ->end()
            ->end()
            ->arrayNode('destinations_php')->fixXmlConfig('destination_php')
            ->cannotBeEmpty()->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('scalar')
            ->end()
            ->end()
            ->arrayNode('destinations_jms')->fixXmlConfig('destination_jms')
            ->cannotBeEmpty()->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('scalar')
            ->end()
            ->end()
            ->arrayNode('aliases')->fixXmlConfig('alias')
            ->prototype('array')
            ->prototype('scalar')
            ->end()
            ->end()
            ->end()
            ->arrayNode('metadata')->fixXmlConfig('metadata')
            ->cannotBeEmpty()->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('scalar')->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
