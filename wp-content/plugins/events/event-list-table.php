<?php

class ReservationListTable extends WP_List_Table {

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $data = $this->get_data();

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    public function get_columns()
    {
        $columns = array(
            'id' => 'ID',
            'first_name' => 'Nom',
            'last_name' => 'Prenom',
			'telephone' => 'Telephone',
            'name_event' => 'Nom evenement'
        );

        return $columns;
    }

    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'id':
            case 'first_name':
            case 'last_name':
			case 'telephone':
            case 'name_event':
                return $item[ $column_name ];

            default:
                return print_r( $item, true ) ;
        }
    }

    public function get_data()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservations';

        $result = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A);

        return $result;
    }

}