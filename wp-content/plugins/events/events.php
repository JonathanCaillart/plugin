<?php
/*
Plugin Name: Reservations
Description: reservation plugin
Author: CJ
Version: 1.0.0
*/

function events_init() {
	// CPT Event
	$labels = array(
	  'name' => 'Events',
	  'all_items' => 'Tous les évènements',
	  'singular_name' => 'Event',
	  'add_new_item' => 'Ajouter un évènement',
	  'menu_name' => 'Events'
	);
  
	$args = array(
	  'labels' => $labels,
	  'public' => true,
	  'show_in_rest' => true,
	  'has_archive' => true,
	  'rewrite' => array("slug" => "events"),
	  'supports' => array('title', 'editor','thumbnail'),
	  'menu_position' => 5,
	  'menu_icon' => 'dashicons-calendar',
	);
  
	register_post_type( 'events', $args );
}
  
add_action('init', 'events_init');
  
// Add meta box date to event
function add_event_date_meta_box() {
	function event_date($post) {
	  $date = get_post_meta($post->ID, 'event_date', true);
  
	  if (empty($date)) $date = the_date();
  
	  echo '<input type="date" name="event_date" value="' . $date  . '" />';
	}

  function event_place($post) {
	  $place = get_post_meta($post->ID, 'event_place', true);
  
	  echo '<input type="number" name="event_place" value="' . $place  . '" />';
	}
  
	add_meta_box('event_date_meta_boxes', 'Date', 'event_date', 'events', 'side', 'default');
  add_meta_box('event_place_meta_boxes', 'Place', 'event_place', 'events', 'side', 'default');
}
  
add_action('add_meta_boxes', 'add_event_date_meta_box');


  
function events_post_save_meta($post_id) {
	if(isset($_POST['event_date']) && $_POST['event_date'] !== "") {
	  update_post_meta($post_id, 'event_date', $_POST['event_date']);
	}
}
  
add_action('save_post', 'events_post_save_meta');

  
// Add event post type to home and main query
  function add_event_post_type($query) {
	if (is_home() && $query->is_main_query()) {
	  $query->set('post_type', array('post', 'events'));
	  return $query;
	}
}
  
add_action('pre_get_posts', 'add_event_post_type');
  
// Short code to display event date meta data
function show_event_date() {
	ob_start();
	$date = get_post_meta(get_the_ID(), 'event_date', true);
	echo "<date>$date</date>";
	return ob_get_clean();
}
  
add_shortcode('show_event_date', 'show_event_date');




function reservation_database(){
    global $wpdb; 

    $table_name = $wpdb->prefix . 'reservations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint (9) NOT NULL AUTO_INCREMENT,
        first_name varchar (55) NOT NULL, 
        last_name varchar (55) NOT NULL, 
        name_event varchar(30) NOT NULL, 
		telephone varchar(10) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option('reservation_db_version' , '1.0');
}

register_activation_hook(__FILE__, 'reservation_database');

function insert_reservation(){
  global $wpdb; 

  $table_name = $wpdb->prefix .'reservations';

  $sql = "INSERT INTO $table_name (first_name, last_name, name_event, telephone) VALUES ('Jhon', 'Doe', 'event test' , '8888888');";

  require_once(ABSPATH . 'wp-admin/includes/update.php');
  dbDelta($sql);
  add_option('reservation_db_version' , '1.0');
}

register_activation_hook(__FILE__, 'insert_reservation');


function add_plugin_reservation_to_admin(){
  function reservation_content(){
      echo "<h1> Reservations </h1>";
      echo "<div style='margin-right:20px'>";

      if(class_exists( 'WP_List_Table' ) ) {
          require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
          require_once(plugin_dir_path(__FILE__). 'event-list-table.php');
          $reservationListTable = new ReservationListTable();
          $reservationListTable->prepare_items();
          $reservationListTable->display();
      } else {
          echo "WP_List_Table n'est pas disponible";
      }

      echo "</div>";
  }
  

  add_menu_page('Reservations', 'Reservations', 'manage_options', 'reservation-plugin', 'reservation_content');

  function reservation_form() {

    global $wpdb;

    $table_name = $wpdb->prefix . 'posts';
    $results = $wpdb->get_results("SELECT * FROM $table_name WHERE post_type = 'events' AND post_status = 'publish';", ARRAY_A);
    
    if (isset($_REQUEST['id'])) {
      $table_name = $wpdb->prefix . 'reservations';
      $reservation = $wpdb -> get_row($wpdb -> prepare("SELECT * FROM $table_name WHERE id = %d ", $_REQUEST ['id']));
    }

    echo "<h1>Réservation</h1>";
    echo "<form method='POST' style='padding-top: 45px !important'>";
    echo "<input type='text' name='first_name' placeholder='Prénom' " . (!isset($reservation) ? "" : "value='" . $reservation->first_name . "'") . " required><br>";
    echo "<input type='text' name='last_name' placeholder='Nom de famille' " . (!isset($reservation) ? "" : "value='" . $reservation->last_name . "'") . " required><br>";
    echo "<input type='tel' name='telephone' placeholder='Numéro de téléphone' " . (!isset($reservation) ? "" : "value='" . $reservation->telephone . "'") . " required><br>";
    echo "<select name='name_event'>";
    foreach ($results as $result) {
      echo "<option value='" . $result['post_title'] . "' " . (isset($reservation) && $reservation->post_id == $result['ID'] ? "selected" : "") . ">" . $result['post_title'] . "</option>";
    }
    echo "<input type='submit' name='reservation' value='Envoyez'>";
    echo "</form>";
    echo "</div>";

    if (isset($_POST['reservation'])) {
      $first_name = sanitize_text_field($_POST['first_name']);
      $last_name = sanitize_text_field($_POST['last_name']);
      $name_event = sanitize_text_field($_POST['name_event']);
      $telephone = sanitize_text_field($_POST['telephone']);


      
      if (!empty($first_name) && !empty($last_name) && !empty($name_event)) {
        $table_name = $wpdb->prefix . 'reservations';

      if (isset($reservation)) {
        $wpdb->update( 
          $table_name,
          array( 
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $telephone,
            'post_id' => $name_event,
          ),
          array( 
            'id' => $reservation->id,
          ),
        );

        echo "<h4>Réservation mise à jour.</h4>";

      } else {

        $wpdb->insert(
          $table_name, array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'name_event' => $name_event,
            'telephone' => $telephone
          )
        );

        echo 'Merci pour votre inscription !';
      }

      }

    }

}

  add_submenu_page('reservation-plugin', 'Reservation', 'Ajouter' , 'edit_posts' , 'reservation' , 'reservation_form');


  

}

add_action('admin_menu', 'add_plugin_reservation_to_admin');


// function reservation_form(){
//     ob_start();
//     global $wpdb;

//     if (isset($_POST['reservation'])) {
//         $first_name = sanitize_text_field($_POST['first_name']);
//         $last_name = sanitize_text_field($_POST['last_name']);
//         $name_event = sanitize_text_field($_POST['name_event']);
// 		$telephone = sanitize_text_field($_POST['telephone']);

        
//         if (!empty($first_name) && !empty($last_name) && !empty($name_event)) {
//           $table_name = $wpdb->prefix . 'reservations';

//           $wpdb->insert(
//             $table_name, array(
//               'first_name' => $first_name,
//               'last_name' => $last_name,
//               'name_event' => $name_event,
// 			  'telephone' => $telephone
//             )
//           );

//           echo 'Merci pour votre inscription !';
//         }

//     }

//     $table_name = $wpdb->prefix . 'posts';
//     $results = $wpdb->get_results("SELECT * FROM $table_name WHERE post_type = 'events' AND post_status = 'publish';", ARRAY_A);
    
    
//     echo '<form method="POST">
//     <input type="text" name="first_name" class="form-control" placeholder="Prénom" required/>
//     <input type="text" name="last_name" class="form-control" placeholder="Nom de famille" required/>
// 	<input type="text" name="telephone" class="form-control" placeholder="Télephone" required/>
//     <select name="name_event" class="form-select">
//         <option value=""> Choisir un evenement </option>'; 
//         foreach ($results as $result) {
//         echo '<option value="'. $result["post_title"] .'">'. $result["post_title"] . '</option>';
//         };
//     echo '</select>
//     <input type="submit" name="reservation" class="btn btn-primary" value="Envoyer"/>
//     </form>';

//     return ob_get_clean();
// }


add_shortcode('reservation_form', 'reservation_form');

function get_events() {
  ob_start();
  global $wpdb;

  $table_name = $wpdb->prefix . 'posts';
  $results = $wpdb->get_results("SELECT * FROM $table_name WHERE post_type = 'events' AND post_status = 'publish';", ARRAY_A);
  

  echo '
  <div class="container mb-3">
    <table class="table table-hover">
        <thead>
            <tr>
                <th class="cellule_designation">ID</th>
                <th class="cellule_designation">Evenement </th>
                <th class="cellule_designation">Date </th>
                <th class="cellule_designation">Inscription</th>
            </tr>
        </thead>

        <tbody>';
            foreach($results as $result) {
            echo '<tr>
                <td class="cellule_table">'.
                    $result['ID'] .' 
                </td>

                <td class="cellule_table">' .
                    $result['post_title'] .'
                </td>

                <td class="cellule_table">'.
                    $result['post_date'] .'
                </td>

                <td class="cellule_button">
                    <button type="button" class="btn btn-light" style="background-color:grey;">
                        <a href="'.get_the_permalink($result['ID']).'"
                            style="text-decoration:none; color:black;">Inscription
                        </a>
                    </button>
                </td>
            </tr>';
            }; 
        echo '</tbody>
    </table>
  </div>
  ';
}

add_shortcode('get_events', 'get_events');