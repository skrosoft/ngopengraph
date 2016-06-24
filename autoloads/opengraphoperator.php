<?php

class OpenGraphOperator
{
    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var eZINI
     */
    private $ogIni;

    /**
     * @var bool
     */
    private $facebookCompatible = false;

    /**
     * Constructor
     */
    function OpenGraphOperator()
    {
        $this->Operators = array( 'opengraph', 'language_code' );
    }

    /**
     * Returns configured operators
     *
     * @return array
     */
    function &operatorList()
    {
        return $this->Operators;
    }

    /**
     * Returns if template operators support named parameters
     *
     * @return bool
     */
    function namedParameterPerOperator()
    {
        return true;
    }

    /**
     * Returns definition of named parameters
     *
     * @return array
     */
    function namedParameterList()
    {
        return array(
            'opengraph' => array(
                'nodeid' => array(
                    'type' => 'integer',
                    'required' => true,
                    'default' => 0
                )
            ),
            'language_code' => array()
        );
    }

    /**
     * Executes the operators
     *
     * @param eZTemplate $tpl
     * @param string $operatorName
     * @param array $operatorParameters
     * @param string $rootNamespace
     * @param string $currentNamespace
     * @param mixed $operatorValue
     * @param array $namedParameters
     */
    function modify( &$tpl, &$operatorName, &$operatorParameters, &$rootNamespace,
                     &$currentNamespace, &$operatorValue, &$namedParameters )
    {
       switch ( $operatorName )
       {
            case 'opengraph':
            {
                $operatorValue = $this->generateOpenGraphTags( $namedParameters['nodeid'] );
                break;
            }
            case 'language_code':
            {
                $operatorValue = eZLocale::instance()->httpLocaleCode();
                break;
            }
       }
    }

    /**
     * Executes opengraph operator
     *
     * @param int|eZContentObjectTreeNode $nodeID
     *
     * @return array
     */
    function generateOpenGraphTags( $nodeID )
    {
        $this->ogIni = eZINI::instance( 'ngopengraph.ini' );
        $this->facebookCompatible = $this->ogIni->variable( 'General', 'FacebookCompatible' );
        $this->debug = $this->ogIni->variable( 'General', 'Debug' ) == 'enabled';

        $availableClasses = $this->ogIni->variable( 'General', 'Classes' );

        if ( $nodeID instanceof eZContentObjectTreeNode )
        {
            $contentNode = $nodeID;
        }
        else
        {
            $contentNode = eZContentObjectTreeNode::fetch( $nodeID );
            if ( !$contentNode instanceof eZContentObjectTreeNode )
            {
                return array();
            }
        }

        $contentObject = $contentNode->object();

        if ( !$contentObject instanceof eZContentObject || !in_array( $contentObject->contentClassIdentifier(), $availableClasses ) )
        {
            return array();
        }

        $returnArray = $this->processGenericData( $contentNode );

        $returnArray = $this->processObject( $contentObject, $returnArray );

        if ( $this->checkRequirements( $returnArray ) )
        {
            return $returnArray;
        }
        else
        {
            if ( $this->debug )
            {
                eZDebug::writeDebug( 'No', 'Facebook Compatible?' );
            }

            return array();
        }
    }

    /**
     * Processes literal Open Graph metadata
     *
     * @param eZContentObjectTreeNode $contentNode
     *
     * @return array
     */
    function processGenericData( $contentNode )
    {
        $returnArray = array();

        $siteName = trim( eZINI::instance()->variable( 'SiteSettings', 'SiteName' ) );
        if ( !empty( $siteName ) )
        {
            $returnArray['og:site_name'] = $siteName;
        }

        $urlAlias = $contentNode->urlAlias();
        eZURI::transformURI( $urlAlias, false, 'full' );
        $returnArray['og:url'] = $urlAlias;

        if ( $this->facebookCompatible == 'true' )
        {
            $appID = trim( $this->ogIni->variable( 'GenericData', 'app_id' ) );
            if ( !empty( $appID ) )
            {
                $returnArray['fb:app_id'] = $appID;
            }

            $defaultAdmin = trim( $this->ogIni->variable( 'GenericData', 'default_admin' ) );
            $data = '';
            if ( !empty( $defaultAdmin ) )
            {
                $data = $defaultAdmin;

                $admins = $this->ogIni->variable( 'GenericData', 'admins' );

                if ( !empty( $admins ) )
                {
                    $admins = trim( implode( ',', $admins ) );
                    $data = $data . ',' . $admins;
                }
            }

            if ( !empty( $data ) )
            {
                $returnArray['fb:admins'] = $data;
            }
        }

        return $returnArray;
    }

    /**
     * Processes Open Graph metadata from object attributes
     *
     * @param eZContentObject $contentObject
     * @param array $returnArray
     *
     * @return array
     */
    function processObject( $contentObject, $returnArray )
    {
        if ( $this->ogIni->hasVariable( $contentObject->contentClassIdentifier(), 'LiteralMap' ) )
        {
            $literalValues = $this->ogIni->variable( $contentObject->contentClassIdentifier(), 'LiteralMap' );
            if ( $this->debug )
            {
                eZDebug::writeDebug( $literalValues, 'LiteralMap' );
            }

            if ( $literalValues )
            {
                foreach ( $literalValues as $key => $value )
                {
                    if ( !empty( $value ) )
                    {
                        $returnArray[$key] = $value;
                    }
                }
            }
        }

        if ( $this->ogIni->hasVariable( $contentObject->contentClassIdentifier(), 'AttributeMap' ) )
        {
            $attributeValues = $this->ogIni->variableArray( $contentObject->contentClassIdentifier(), 'AttributeMap' );
            if ( $this->debug )
            {
                eZDebug::writeDebug( $attributeValues, 'AttributeMap' );
            }

            if ( $attributeValues )
            {
                foreach ( $attributeValues as $key => $value )
                {
                    $contentObjectAttributeArray = $contentObject->fetchAttributesByIdentifier( array( $value[0] ) );
                    if ( !is_array( $contentObjectAttributeArray ) )
                    {
                        continue;
                    }

                    $contentObjectAttributeArray = array_values( $contentObjectAttributeArray );
                    $contentObjectAttribute = $contentObjectAttributeArray[0];

                    if ( $contentObjectAttribute instanceof eZContentObjectAttribute )
                    {
                        $openGraphHandler = ngOpenGraphBase::getInstance( $contentObjectAttribute );

                        if ( count( $value ) == 1 )
                        {
                            $data = $openGraphHandler->getData();
                        }
                        else if ( count( $value ) == 2 )
                        {
                            $data = $openGraphHandler->getDataMember( $value[1] );
                        }
                        else
                        {
                            $data = "";
                        }

                        if ( !empty( $data ) )
                        {
                            $returnArray[$key] = $data;
                        }
                    }
                }
            }
        }

        return $returnArray;
    }

    /**
     * Checks if all required Open Graph metadata are present
     *
     * @param array $returnArray
     *
     * @return bool
     */
    function checkRequirements( $returnArray )
    {
        $arrayKeys = array_keys( $returnArray );

        $required_tags = $this->ogIni->variable( 'General', 'RequiredTags' );

        if ( count(array_diff($required_tags, $arrayKeys)) > 0 )
        {
            if ( $this->debug )
            {
                eZDebug::writeError( $arrayKeys, 'Missing an OG required field: ' . implode(', ', $required_tags) );
            }

            return false;
        }

        if ( $this->facebookCompatible == 'true' )
        {
            if ( !in_array( 'og:site_name', $arrayKeys ) || ( !in_array( 'fb:app_id', $arrayKeys ) && !in_array( 'fb:admins', $arrayKeys ) ) )
            {
                if ( $this->debug )
                {
                    eZDebug::writeError( $arrayKeys, 'Missing a FB required field (in ngopengraph.ini): app_id, DefaultAdmin, or site name (site.ini)' );
                }

                return false;
            }
        }

        return true;
    }
}
