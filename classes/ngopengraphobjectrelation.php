<?php

class ngOpenGraphObjectRelation extends ngOpenGraphBase
{
    /**
     * Returns data for the attribute
     *
     * @return string
     */
    public function getData()
    {
        $relationObject = $this->ContentObjectAttribute->attribute( 'content' );

        if ( $relationObject instanceof eZContentObject )
        {
            return trim( $relationObject->attribute( 'name' ) );
        }

        return "";
    }

    /**
     * Returns part of the data for the attribute
     *
     * @param string $dataMember
     *
     * @return string
     */
    public function getDataMember( $dataMember )
    {
        if ( $dataMember === 'related_images' )
        {
            $images  = array();

            $relationObject = $this->ContentObjectAttribute->attribute( 'content' );
            if ( $relationObject instanceof eZContentObject )
            {
                $dataMap = $relationObject->attribute( 'data_map' );
                foreach ( $dataMap as $attribute )
                {
                    /** @var eZContentObjectAttribute $attribute */
                    if ( $attribute->attribute( 'data_type_string' ) !== eZImageType::DATA_TYPE_STRING )
                    {
                        continue;
                    }

                    if ( $attribute->hasContent() )
                    {
                        $imageAliasHandler = $attribute->attribute( 'content' );
                        $imageAlias = $imageAliasHandler->imageAlias( 'opengraph' );
                        if ( $imageAlias['is_valid'] == 1 )
                        {
                            $images[] = eZSys::serverURL() . '/' . $imageAlias['full_path'];
                        }
                    }
                }
            }

            if ( empty( $images ) )
            {
                $images[] = eZSys::serverURL() . eZURLOperator::eZImage( null, 'opengraph_default_image.png', '' );
            }

            return $images;
        }

        return "";
    }
}
