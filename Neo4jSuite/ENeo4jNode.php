<?php
/**
 * @author Johannes "Haensel" Bauer
 * @since version 0.1
 * @version 0.1
 */

/**
 * ENeo4jNode represents a node in an Neo4j graph database. Every node is automatically indexed by default
 * This is especially important as we need an attribute (by default:modelclass) to determine how to instantiate
 * the node.
 */
class ENeo4jNode extends ENeo4jPropertyContainer
{

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Get the rest() info of the PropertyContainer and define the resource as "node"
     * @return <type>
     */
    public function rest()
    {
        return CMap::mergeArray(
            parent::rest(),
            array('resource'=>'node')
        );
    }
    
    /**
     * Finds a single property container with the specified id within the modelclass index.
     * @param mixed $id The id.
     * @return ENeo4jPropertyContainer the property container found. Null if none is found.
     */
    public function findById($id)
    {
            Yii::trace(get_class($this).'.findById()','ext.Neo4jSuite.ENeo4jNode');
            $gremlinQuery=new EGremlinScript;
            $gremlinQuery->setQuery('g.v('.$id.').filter{it.'.$this->getModelClassField().'=="'.get_class($this).'"}');
            $response=$this->getGraphService()->queryByGremlin($gremlinQuery)->getData();
            if(isset($response[0]) && is_array($response[0]))
            {
                $model=$this->populateRecords($response);
                return $model[0];
            }
            else
                return null;
    }

    /**
     * Nodes are created differently to relationships, so we override the ActiveResource method here.
     * @param array $attributes The attributes to be used when creating the node
     * @return boolean true on success, false on failure
     */
    public function create($attributes=null)
     {
        if(!$this->getIsNewResource())
            throw new EActiveResourceException('The node cannot be inserted because it is not new.');
        if($this->beforeSave())
        {
            Yii::trace(get_class($this).'.create()','ext.Neo4jSuite.ENeo4jNode');

            $response=$this->postRequest(null, $this->getAttributes());
            
            $responseData=$response->getData();

            $returnedmodel=$this->populateRecord($response->getData());

            if($returnedmodel)
            {
                $id=$this->idProperty();
                $this->$id=$returnedmodel->getId();
            }

            $this->afterSave();
            $this->setIsNewResource(false);
            $this->setScenario('update');
            return true;

        }
        return false;
     }

    /**
     * Returns an array of incoming relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: incoming('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is pecified all incoming relationships will be returned
     * @param string $types The types you want to get
     * @return array An array of ENeo4jRelationship objects
     */
    public function incoming($types=null)
    {
        return $this->getRelationships($types,$direction='in');
    }

    /**
     * Returns an array of outgoing relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: outgoing('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is specified all outgoing relationships will be returned
     * @param string $types The types you want to get
     * @return array An array of ENeo4jRelationship objects
     */
    public function outgoing($types=null)
    {
        return $this->getRelationships($types,$direction='out');

    }

    /**
     * Returns an array of relationships. You can specify which relationships you want by adding
     * them with a ',' seperator. e.g.: incoming('HAS_ATTRIBUTE,'HAS_NAME'')
     * If no type is pecified all relationships will be returned
     * You can also define the direction of the relationships by using one of the three options for direction ('all','in','out')
     * @param string $types The types you want to get
     * @param string $direction The direction of the relationships
     * @return array An array of ENeo4jRelationship objects
     */
    public function getRelationships($types=null,$direction='all')
    {
        Yii::trace(get_class($this).'.getRelationships()','ext.Neo4jSuite.ENeo4jNode');
        $uri=$this->getSite().'/'.$this->getResource().'/'.$this->getId().'/relationships';
        if($direction)
            $uri.='/'.$direction;
        if($types)
        {
            if(is_array($types))
            {
                $types=implode('&',$types);
                $uri.='/'.$types;
            }
            else
                $uri.='/'.$types;
        }
        
        $response=ENeo4jRelationship::model()->customGetRequest($uri);
        
        return ENeo4jRelationship::model()->populateRecords($response->getData());
    }

    /**
     * Add a relationship to another node
     * @param ENeo4jNode $node The node object to connect with (will be the endNode of the relationship)
     * @param string $type The type of this relationship. something like 'HAS_NAME'
     * @param array $properties An array of properties used for the relationship. e.g.: array('since'=>'2010')
     */
    public function addRelationshipTo(ENeo4jNode $node,$type,$properties=null)
    {
        Yii::trace(get_class($this).'.addRelationshipTo()','ext.Neo4jSuite.ENeo4jNode');
        $relationship=new ENeo4jRelationship;
        $relationship->setStartNode($this);
        $relationship->setEndNode($node);
        $relationship->type=$type;
        $relationship->setAttributes($properties,false);
        $relationship->save();
    }

}

?>
