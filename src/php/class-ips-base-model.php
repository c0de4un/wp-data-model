<?php

// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =
// NAMESPACE
// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =
// USE
// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =
// CLASS
// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

abstract class IPSBaseModel implements JsonSerializable
{

    // = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                       META & TRAITS
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
	
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                        CONSTANTS
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                          FIELDS
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    /** @var String. Used to automate serialization from SQL-query result. */
    protected $model_class = null;

    /** @var string Table name, must be overridden by implementor (derriver). */
    protected $table_name = null;

    /** @var Integer IPS-Row.ID */
    public $id = null;

    /**
     * @var Array[String]
     * Used for SQL-query filtering.
     */
    protected $field_types = [
    ];

    /**
     * @var Array[String]
     * Used to automate save/load operations via SQL-queries.
     * Works with #get_attr to retireve instance (implementor/deriver)
     * specific logic for fields.
     */
    protected $sql_serializable_fields = [
    ];

    /** @var Array[String] fields to hide from json. */
    protected $hidden = [
    ];

    /** @var Array[String] attributes. */
    protected $attrs = [
    ];

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                        CONSTRUCTOR
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    /**
     * @param WPDB $wpdb - to automate serialization/deserialization.
     * @param String $table_name - associated SQL table name.
     * @param String derriver-class fulle name (with namespace, if have one).
     */
    function __construct( $wpdb, $table_name, $in_model_class )
    {
        $this->table_name = self::get_table( $wpdb, $table_name );
        $this->model_class = $in_model_class;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                       METHODS.PUBLIC
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    /**
     * Strict equality comparsion of colum-value & given one.
     * 
     * (?) $WPDB::prepare is used.
     * 
     * @param String $field - column-name.
     * @param String $type - cell data-type. Only %s (string) && %d (decimal) are allowed.
     * @param Integer||String $value - dst-value.
     */
    static function get_where( $wpdb, $table_name, $type, $field, $value )
    {
        $output = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE $field = $type", $value ), ARRAY_A );
        return empty($output) ? [] : $output;
    }    

    static function get_where_ex( $wpdb, $field, $value, $type, $table_name, $class )
    {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE $field = $type", $value ), ARRAY_A );
        return self::serialize_from_array( $row, $wpdb, $class );
    }

    /**
     * Returns instances loaded using query.
     * 
     * (!) Query must be filtered.
     * 
     * @param WPDB $wpdb
     * @param String $query
     * @param String $class
     */
    static function get_all_by_query_ex( $wpdb, $query, $class )
    {
        $rows = $wpdb->get_results( $query, ARRAY_A );
        return self::serialize_instances( $wpdb, $rows, $class );
    }

    /**
     * Returns instance loaded using query.
     * 
     * (!) Query must be filtered.
     * 
     * @param WPDB $wpdb
     * @param String $query
     * @param String $class
     */
    static function get_by_query_ex( $wpdb, $query, $class )
    {
        $rows = $wpdb->get_results( $query, ARRAY_A );
        $array = self::serialize_instances( $wpdb, $rows, $class );

        return !empty($array) ? $array[0] : null;
    }

    static function get_all_where_ex( $wpdb, $table_name, $field, $value, $type, $class )
    {
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE $field = '$type'", $value );
        $rows = $wpdb->get_results( $query, ARRAY_A );
        return self::serialize_instances( $wpdb, $rows, $class );
    }

    static function serialize_instances( $wpdb, $rows, $class )
    {
        $output = [];
        if ( !empty($rows) ) {
            foreach( $rows as $row )
            {
                $output []= self::serialize_from_array( $row, $wpdb, $class );
            }
        }

        return $output;
    }

    static function get_all_ex( $wpdb, $table_name, $class )
    {
        $rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );

        return self::serialize_instances( $wpdb, $rows, $class );
    }

    static function load_arrays( $wpdb, $table_name, $field, $value )
    {
        $output = $wpdb->get_results( "SELECT * FROM $table_name WHERE $field = '$value'", ARRAY_A );
        return empty($output) ? [] : $output;
    }

    static function get_table( $wpdb, $table_name )
    {
        return $wpdb->prefix.($table_name);
    }

    /**
     * @virtual
     */
    protected function onDelete( $wpdb )
    {
    }

    /**
     * Deleted rows using.
     * 
     * (!)
     * Use only if you don't need deletion-handle logic.
     * 
     * @param WPDB $wpdb
     * @param String $table_name
     * @param Array[key=>value] $args
     */
    static function raw_delete( $wpdb, $table_name, $args )
    {
        $wpdb->delete( $table_name, $args );
    }

    function delete( $wpdb )
    {
        if ( is_null($this->id) ) {
            return;
        }

        // Allow implementors/derrivers to handle deletion.
        $this->onDelete( $wpdb );

        $wpdb->delete( $this->table_name, ['id' => $this->id] );
    }

    /**
     * Tries to cast value to readable alias.
     * 
     * @virtual
     * 
     * @return String
     */
    function get_data_title( $data_slug, $value )
    {
        return $value;
    }

    /**
     * Accesser for attributes/fields of data-model.
     * 
     * (!) Virtual method.
     * 
     * @param String $attr_name
     * @param ? $default = null
     * @param String $context = 'sql'  || 'data' || 'view' (for human-readable)
     */
    function get_attribute( $attr_name, $default = null, $context = 'data' )
    {
        if ( empty($attr_name) ) {
            return $default;
        }

        foreach( $this as $key => $value ) {
            if ( $key === $attr_name ) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @Virtual
     * @param String $field
     * @param mix $value
     * @param String $context @example: 'input' || 'service'
    */
    function set_attribute( $field, $value, $context ): void
    {
    }

    /**
     * @Virtual
     */
    function get_data( $field, $valie ): void
    {
    }

    /**
     * @Virtual
     */
    function set_data( $field, $value ): void
    {
        if ( isset($this->attrs[$field]) ) {
            $this->set_attribute( $field, $value );
            return;
        }

        if ( isset($this->$field) ) {
            $this->field = $value;
        }
    }

    /**
     * (!) @virtual
     * 
     * @param String $context (@example "view" || "edit")
     * 
     * @return Array[key=>value]
     */
    function toArray( $wpdb, $context ): array
    {
        return (array)$this;
    }

    /** @see JsonSerializable */
    public function jsonSerialize()
    {
        $output = [];
        foreach( $this as $key => $value )
        {
            if ( in_array($key, $this->hidden) ) {
                continue;
            }

            if ( in_array($key, $this->attrs) ) {
                $output []= [
                    $key => json_encode( $this->get_attribute($key, $value) ),
                ];
            } else {
                $output []= [
                    $key => json_encode( $value ),
                ];
            }
        }

        return $output;
    }

    function update_fields_from_input_array( $in_array )
    {
        foreach( $this as $key => $value )
        {
            if ( isset($in_array[$key]) ) {
                $this->$key = $in_array[$key];
            }
        }
    }

    /**
     * @virtual
     */
    // function save( $wpdb )
    // {
    //     $table_data = [];

    //     // Gather values.
    //     foreach( $this->sql_serializable_fields as $attr_name )
    //     {
    //         $table_data []= [
    //             $attr_name => $this->get_attribute( $attr_name, null, 'sql' ),
    //         ];
    //     }

    //     if ( is_null($this->id) ) {
    //         var_dump( $table_data ); // @TODO: FIX Array is transferred, when string expected
    //         $wpdb->insert( $this->table_name, $table_data, $this->field_types );
    //         $this->id = $wpdb->insert_id;
    //     } else {
    //         $wpdb->update( $this->table_name, $table_data, [ 'id' => $this->id ] );
    //     }
    // }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                     METHODS.PROTECTED
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    /**
     * @virtual
     */
    protected function on_serialized( $wpdb )
    {
    }

    protected static function serialize_from_array( $array, $wpdb, $class_name )
    {
        if ( empty($array) ) {
            return null;
        }

        $instance = new $class_name( $wpdb );
        foreach( $instance as $key => $value )
        {
            if ( isset($array[$key]) ) {
                $instance->$key = $array[$key];
            }
        }

        if ( method_exists($instance, 'on_serialized') ) {
            $instance->on_serialized( $wpdb );
        }

        return $instance;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //                      METHODS.PRIVATE
    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    // = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =

}

// = = = = = = = = = = = = = = = = = = = = = = = = = = = = = =
