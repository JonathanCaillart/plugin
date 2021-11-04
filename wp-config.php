<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d’installation. Vous n’avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en « wp-config.php » et remplir les
 * valeurs.
 *
 * Ce fichier contient les réglages de configuration suivants :
 *
 * Réglages MySQL
 * Préfixe de table
 * Clés secrètes
 * Langue utilisée
 * ABSPATH
 *
 * @link https://fr.wordpress.org/support/article/editing-wp-config-php/.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define( 'DB_NAME', 'plugin' );

/** Utilisateur de la base de données MySQL. */
define( 'DB_USER', 'root' );

/** Mot de passe de la base de données MySQL. */
define( 'DB_PASSWORD', '' );

/** Adresse de l’hébergement MySQL. */
define( 'DB_HOST', 'localhost' );

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/**
 * Type de collation de la base de données.
 * N’y touchez que si vous savez ce que vous faites.
 */
define( 'DB_COLLATE', '' );

/**#@+
 * Clés uniques d’authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clés secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n’importe quel moment, afin d’invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'b6D3H*d8B<L$+PgJy-j5$uuxe8 3W e.TW+sp}sUT}aiu4Wl=|>83qQsYA~b;&A,' );
define( 'SECURE_AUTH_KEY',  '_0ruqSe<qj[KK+}k[H[Fn+km+U1Y4?M4J|Zw7.=:$I:N3CWp*Rr<l/|+N~asoIML' );
define( 'LOGGED_IN_KEY',    ',}ql^)dOg)$g^pez;El:X?E+h?Mr)?A2]i&NL}Cj>sV8h@!eu:+%<d0>vezl1,{-' );
define( 'NONCE_KEY',        ':dlDbJrw@gK!`x8,?g6}r%@eYl/xDn-G^4uAuF,FA,8Yoj Cz0dC{rD>G{F ,&;i' );
define( 'AUTH_SALT',        'Oj5cd?xmms3**=lCponjg@g8]P~V QG(-mncjW @)0M|}U_wv3Z_fx d0O,;Nk:3' );
define( 'SECURE_AUTH_SALT', '6ki#%1D<5B=zibSu6TG|klyS%cqKk@5Tt6q08x;-g67DuO ?hynQ~Z6cguJc~Cj2' );
define( 'LOGGED_IN_SALT',   'saKP:On&Epc8wBrBsz7dC5Tx*%Wj$ea~NLzXvDl(t+IU~rv~JXLZP6?nBFHn89&<' );
define( 'NONCE_SALT',       'IhJgKiv,qi8#glUOARQ{Q+{_@zm&*S_+:d_cH4qf^Dm??]G-PIwYsbnTfE,L/Ee3' );
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N’utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés !
 */
$table_prefix = 'wp_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l’affichage des
 * notifications d’erreurs pendant vos essais.
 * Il est fortement recommandé que les développeurs d’extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d’information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 *
 * @link https://fr.wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* C’est tout, ne touchez pas à ce qui suit ! Bonne publication. */

/** Chemin absolu vers le dossier de WordPress. */
if ( ! defined( 'ABSPATH' ) )
  define( 'ABSPATH', dirname( __FILE__ ) . '/' );

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once( ABSPATH . 'wp-settings.php' );
