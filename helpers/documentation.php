<?php
/**
 * @package midgardmvc_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * MidCOM documentation helper
 *
 * @package midgardmvc_core
 */
class midgardmvc_core_helpers_documentation
{
    public static function get_midgard_type_signature($type)
    {
        switch ($type)
        {
            case MGD_TYPE_STRING:
                return 'string';
            case MGD_TYPE_INT:
                return 'integer';
            case MGD_TYPE_UINT:
                return 'unsigned integer';
            case MGD_TYPE_FLOAT:
                return 'float';
            case MGD_TYPE_BOOLEAN:
                return 'boolean';
            case MGD_TYPE_TIMESTAMP:
                return 'midgard_datetime';
            case MGD_TYPE_LONGTEXT:
                return 'longtext';
            case MGD_TYPE_GUID:
                return 'GUID';
        }
    }

    public static function get_property_documentation($class)
    {
        $property_docs = array();
        $props = array();
        $mgdschemas = midgardmvc_core_midcom::get_instance()->dispatcher->get_mgdschema_classes();
        if (in_array($class, $mgdschemas))
        {
            $dummy = new $class;
            $props = get_object_vars($dummy);
        }
        elseif ($class == 'midgard_metadata')
        {
            $dummy = new midgard_person();
            $props = get_object_vars($dummy->metadata);
        }

        if (empty($props))
        {
            // Non-MgdSchema class, reflect using PHP method
            $reflector = new midgard_reflection_class($class);
            $props = $reflector->getProperties();
            foreach ($props as $property)
            {
                if (!$property->isPublic())
                {
                    // No sense to document private properties
                    continue;
                }
                $property_doc = array
                (
                    'name' => $property->name,
                    'type' => null,
                    'type_url' => null,
                    'link_url' => null,
                    'signature' => $property->name,
                    'documentation' => midgardmvc_core_helpers_documentation::render_docblock($property->getDocComment()),
                );
                $property_docs[] = $property_doc;
            }
            return $property_docs;
        }

        $reflectionproperty = new midgard_reflection_property($class);   
        foreach ($props as $property => $value)
        {
            if ($property == 'action')
            {
                continue;
            }
            
            $type = midgardmvc_core_helpers_documentation::get_midgard_type_signature($reflectionproperty->get_midgard_type($property));
            if (   !$type
                && $property == 'metadata')
            {
                $type = 'midgard_metadata';
            }
            
            $property_doc = array
            (
                'name' => $property,
                'type' => $type,
                'type_url' => null,
                'link_url' => null,
                'signature' => "{$type} {$property}",
                'documentation' => $reflectionproperty->description($property),
            );

            try
            {
                // Link to the class documentation is the property is of particular type
                if (   strpos($type, '_') !== false
                    && class_exists($type))
                {
                    $property_doc['type_url'] = midgardmvc_core_midcom::get_instance()->dispatcher->generate_url('midcom_documentation_class', array('class' => $type));
                }
            }
            catch (Exception $e)
            {
            }
            
            if ($reflectionproperty->is_link($property))
            {
                $property_doc['link_url'] = midgardmvc_core_midcom::get_instance()->dispatcher->generate_url('midcom_documentation_class', array('class' => $reflectionproperty->get_link_name($property)));
                $property_doc['link'] = $reflectionproperty->get_link_name($property) . '::' . $reflectionproperty->get_link_target($property);
            }

            $property_docs[] = $property_doc;
        }
        
        return $property_docs;
    }

    /**
     * Get list of signals applying to the class.
     *
     * @return array Signals applying to a class
     */
    public static function get_signal_documentation($class)
    {
        $signals = array();
        $reflectionclass = new midgard_reflection_class($class);
        $signals = $reflectionclass->listSignals();
        $parent = $reflectionclass->getParentClass();
        if ($parent)
        {
            $parent = new midgard_reflection_class($parent->getName());
            // Add default signals of MgdSchema objects
            $signals = array_merge($signals, $parent->listSignals());
        }
        return $signals;
    }

    public static function get_parameter_documentation(reflectionparameter $reflectionparameter)
    {
        $parameter_documentation = array();
        $parameter_documentation['signature'] = '';                

        try
        {
            // Check if the parameter is typecasted to a class
            $parameterclass = $reflectionparameter->getClass();
            if ($parameterclass)
            {
                $parameter_documentation['signature'] .= $parameterclass->getName() . ' ';
            }
        }
        catch (ReflectionException $e)
        {
            // Method requires a class that is not available
            // TODO: how to react?
        }
                
        if ($reflectionparameter->isArray())
        {
            $parameter_documentation['signature'] .= 'array ';
        }
                
        if ($reflectionparameter->isPassedByReference())
        {
            $parameter_documentation['signature'] .= '&';
        }

        $parameter_documentation['signature'] .= '$' . str_replace(' ', '_', $reflectionparameter->getName());
                
        if ($reflectionparameter->isDefaultValueAvailable())
        {
            $parameterdefault = $reflectionparameter->getDefaultValue();
            if (empty($parameterdefault))
            {
                switch (gettype($parameterdefault))
                {
                    case 'NULL':
                        $parameterdefault = 'null';
                        break;
                    default:
                        $parameterdefault = "''";
                }
            }
            $parameter_documentation['signature'] .= ' = ' . $parameterdefault;
        }

        if ($reflectionparameter->isOptional())
        {
            $parameter_documentation['signature'] = "[{$parameter_documentation['signature']}]";
        }
        
        return $parameter_documentation['signature'];
    }

    public static function get_method_documentation($class, $method)
    {
        $method_documentation = array();
        $method = new midgard_reflection_method($class, $method);
        $arguments = '';
        $parametersdata = array();
        $parameters = $method->getParameters();
        foreach ($parameters as $reflectionparameter)
        {
            $parametersdata[] = midgardmvc_core_helpers_documentation::get_parameter_documentation($reflectionparameter);
        }
        
        $arguments .= '(' . implode(', ', $parametersdata) . ')';
        $modifiers = implode(' ' , Reflection::getModifierNames($method->getModifiers()));
        $methodsignature = "{$modifiers} {$method->name}{$arguments}";
        
        if (strpos($modifiers, 'static') !== false)
        {
            $method_documentation['static'] = true;
            $methodsignature = "{$class}::{$method->name}{$arguments}";
        }

        if (strpos($modifiers, 'abstract') !== false)
        {
            $method_documentation['abstract'] = true;
        }

        $method_documentation['name'] = $method->name;
        $method_documentation['modifiers'] = $modifiers;
        $method_documentation['arguments'] = $arguments;
        $method_documentation['signature'] = $methodsignature;
        $method_documentation['documentation'] = midgardmvc_core_helpers_documentation::render_docblock($method->getDocComment());
        
        return $method_documentation;
    }           

    public static function get_class_documentation(midgard_reflection_class $reflectionclass)
    {
        $class_documentation = array();
        $class_documentation['docblock'] = midgardmvc_core_helpers_documentation::render_docblock($reflectionclass->getDocComment());
        
        $parent_class = $reflectionclass->getParentClass();
        if ($parent_class)
        {
            $class_documentation['extends'] = $parent_class->getName();
            $class_documentation['extends_url'] = midgardmvc_core_midcom::get_instance()->dispatcher->generate_url('midcom_documentation_class', array('class' => $parent_class->getName()));
        }
        
        return $class_documentation;
    }

    /**
     * Simple way to render PHPDoc-blocks to HTML
     *
     * @param string $docblock the PHPDoc definition as written in the code
     * @return string HTML presentation
     */
    static public function render_docblock($docblock)
    {
        if (empty($docblock))
        {
            return $docblock;
        }
        // Just to be sure normalize newlines
        $docblock = preg_replace("/\n\r|\r\n|\r/","\n", $docblock);
        // Strip start and end of comment
        $tmp1 = preg_replace('%/\*\*\s*\n(.*?)\s*\*/%ms', '\\1', $docblock);
        // Strip *s from start of line
        $tmp1 = preg_replace('%^\s*\*\s?%m', '', $tmp1);
        // convert lines of only whitespace to simple newlines
        /**
         * did not work
        $tmp1 = preg_replace('%\s+\n%m', "\n", $tmp1);
         */
        // Entitize significant whitespace
        $ws_matches =  array();
        if (preg_match('%^ {2,}|\t+%m', $tmp1, $ws_matches))
        {
            foreach ($ws_matches as $ws_string)
            {
                $replace = str_replace
                (
                    array
                    (
                        ' ',
                        "\t",
                    ),
                    array
                    (
                        '&nbsp;',
                        "&nbsp;&nbsp;&nbsp;&nbsp;",
                    ),
                    $ws_string
                );
                $tmp1 = str_replace($ws_string, $replace, $tmp1);
            }
        }
        // Separate first line and rest of it
        $parts = explode("\n", $tmp1, 2);
        if (count($parts) === 2)
        {
            $summary = $parts[0];
            $comment = $parts[1];
            $ret = "<div class='summary'>{$summary}</div>\n<div class='comments'>" . nl2br($comment) . "</div>\n";
        }
        else
        {
            $summary = $parts[0];
            $ret = "<div class='summary'>{$summary}</div>\n";
        }
        /*
        echo "DEBUG: ret<pre>\n";
        echo htmlentities($ret);
        echo "</pre>\n";
        */
        return $ret;
    }
}
?>
