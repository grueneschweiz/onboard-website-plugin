<?php
/**
 * Plugin Name:     Gruene Onboard
 * Plugin URI:      https://github.com/grueneschweiz/onboard-website-plugin
 * Description:     Automated onboarding of new websites
 * Author:          grueneschweiz
 * Author URI:      https://gruene.ch
 * Text Domain:     gruene-onboard
 * Version:         1.3.1
 *
 * @package         Gruene_Onboard
 */

namespace Gruene_Onboard;

use WP_CLI;
use function sanitize_title;
use function network_site_url;
use function network_admin_url;

define( 'COMMAND_NAME', 'onboard' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** @noinspection PhpUnhandledExceptionInspection */ // die hard
	WP_CLI::add_command( COMMAND_NAME, '\Gruene_Onboard\Onboarder' );
}

/**
 * Generate new sites for GREENS.
 *
 * @see https://make.wordpress.org/cli/handbook/commands-cookbook/
 *
 * @package Gruene_Clone\Commands
 */
class Onboarder {
	const PERSON_SITE_ID_DE = 4;
	const PERSON_SITE_ID_FR = 8;

	const PLAN_ALL_INVLUSIVE = 'all_inclusive';
	const PLAN_MINIMAL = 'minimal';

	private $person_offer_site_ids = [ 653, 624, 648 ];
	private $person_front_page_id = 513;

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$name_de = 'Peter Muster';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$name_fr = 'Anne Modèle';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$first_name_de = 'Peter';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$first_name_fr = 'Anne';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$email_de = 'mail@petermuster123.ch';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$email_fr = 'mail@annemodele123.ch';

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$person_campaign_cta_de = 'Darum trete ich dem Unterstützungskomitee bei und zeige mit meinem Namen, dass {{first_name}} eine gute Wahl ist.';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$person_campaign_cta_fr = "J'adhère au comité de soutien et je montre par mon nom que {{first_name}} est un bon choix.";

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$send_email_de = 'Email senden';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$send_email_fr = 'Envoyer un e-mail';

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$maintenance_message_de = 'Demnächst Online.';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$maintenance_message_fr = 'Bientôt en ligne.';

	private $person_address = <<<EOL
<b>{{full_name}}</b>
{{city}}

<a class="a-button a-button--primary" href="mailto:{{email}}">{{send_email}}</a>
EOL;

	private $mail_de = <<<EOL
Salut {{first_name}}

Cool, vielen Dank für deine Bestellung. Ich habe deine Website soeben eingerichtet. Sie hat etwas Musterinhalt drauf, den du anpassen oder löschen kannst, so wie es für dich am Einfachsten ist. Die Website ist noch gesperrt, sodass sie nur eingeloggte User sehen können.

{{links}}
Einloggen kannst du dich mit dem GRÜNEN-Login (das gleiche Login wie für den Chat, das Wiki, das CD-Tool etc.). Wenn du noch kein Login hast, kannst du dich selbst registrieren. Klicke dazu einfach auf 'Registrieren' und trage dich mit der folgenden E-Mail-Adresse ein {{email}}.

Eine Anleitung fürs Bearbeiten der Website findest du hier: https://docs.gruene.ch

Wie sieht es aus bezüglich Domain (Internetadresse)? Hast du bereits eine? Falls nein, kannst du beispielsweise bei Infomaniak eine kaufen: https://www.infomaniak.com/de/domains

Damit wir die Website auf deine Domain umstellen können, müsstest du als Nameserver folgendes eintragen:
    • ns1.cyon.ch
    • ns2.cyon.ch
    • ggf. weitere Zeilen löschen / leer lassen
Bitte melde dich vorgängig, damit wir die nötigen Änderungen auch bei der Website vornehmen können.

Unabhängig von der Domain, kannst du bereits jetzt beginnen, deine Inhalte einzufügen etc.

{{support}}

Herzlich,
Cyrill
EOL;

	private $mail_fr = <<<EOL
Bonjour {{first_name}},

Cool, merci pour ta commande. Je viens de mettre en place ton site web. Il contient des exemples de contenu que tu peux personnaliser ou supprimer, selon ce qui est le plus facile pour toi. Le site est toujours verrouillé, de sorte que seuls les utilisateurs connectés peuvent le voir.

{{links}}
Tu peux te connecter avec le login VERT (le même login que pour le chat, le wiki etc.) Si tu n'as pas encore de login, tu peux t'enregistrer. Il suffit de cliquer sur "S'inscrire" sur l'écran de connexion et de saisir l'adresse électronique suivante {{email}}.

Des instructions pour l'édition du site web sont disponibles ici : https://docs.gruene.ch

Quelle est la situation concernant le domaine (adresse internet) ? En as-tu déjà un ? Sinon, tu peux en acheter un chez Infomaniak par exemple : https://www.infomaniak.com/fr/domaines

Pour nous permettre de passer du site web à ton domaine, tu dois entrer ce qui suit comme serveur de nom :
- ns1.cyon.ch
- ns2.cyon.ch
- si nécessaire, supprimer / laisser les lignes supplémentaires vides
Merci de nous contacter à l'avance afin que nous puissions apporter les modifications nécessaires au site web.

Quel que soit le domaine, tu peux déjà commencer à ajouter ton contenu, etc.

{{support}}

Cordialement,
Cyrill
EOL;


	private $support_minimal_de = "Gerne weisen wir nochmals darauf hin, dass bei der Variante Minimal keine Supportanfragen beantwortet werden.";
	private $support_minimal_fr = "Nous tenons à souligner qu'il n'est pas répondu aux demandes d'assistance avec la variante Minimal.";
	private $support_all_inclusive_de = "Melde dich, falls du Hilfe brauchst, insbesondere auch bei der Domain.";
	private $support_all_inclusive_fr = "Pour toute aide, notamment en ce qui concerne le domaine, nous restons à ta disponsition.";


	private $plan;
	private $lang;
	private $first_name;
	private $last_name;
	private $email;
	private $city;
	private $blog_desc;
	private $party_name;
	private $party_url;
	private $fb_url;
	private $tw_name;
	private $insta_url;
	private $admin_email;
	private $site_url;
	private $user_name;
	private $password;


	/**
	 * Clone example site and pre fill it with default content for a person
	 *
	 * ## OPTIONS
	 *
	 * [--plan=<all_inclusive|minimal>]
	 * : The subscription plan the person ordered
	 *
	 * [--lang=<de|fr>]
	 * : The first name of the person
	 *
	 * [--first_name=<first_name>]
	 * : The first name of the person
	 *
	 * [--last_name=<last_name>]
	 * : The last name of the person
	 *
	 * [--email=<email>]
	 * : The email of the person
	 *
	 * [--city=<city>]
	 * : The hometown of the person
	 *
	 * [--blog_description=<blog-description>]
	 * : The tag line
	 *
	 * [--party_name=<party-name>]
	 * : The name of the local party of the person
	 *
	 * [--party_url=<party-url>]
	 * : The url of the local party of the person
	 *
	 * [--facebook_url=<facebook-url>]
	 * : The url to the facebook profile of the person
	 *
	 * [--twitter_name=<twitter-name>]
	 * : The of the person on twitter
	 *
	 * [--instagram_url=<instagram-url>]
	 * : The url to the instagram account of the person
	 *
	 * [--admin_email=<admin-email>]
	 * : The site administrators url
	 *
	 * ## EXAMPLES
	 *
	 * wp onboard person --plan="all_inclusive"
	 *                   --lang=de \
	 *                   --first_name="Peter" \
	 *                   --last_name="Muster" \
	 *                   --email="peter.muster@example.com" \
	 *                   --city="Bern" \
	 *                   --blog_description="Peter Muster in den Nationalrat" \
	 *                   --party_name="GRÜNE Kt. Bern" \
	 *                   --party_url="https://www.gruenebern.ch" \
	 *                   --facebook_url="https://www.facebook.com/petermuster" \
	 *                   --twitter_name="petermuster" \
	 *                   --instagram_url="https://www.instagram.com/petermuster" \
	 *                   --admin_email="admin@example.com"
	 *
	 * wp onboard person --plan="minimal"
	 *                   --lang=de \
	 *                   --first_name="Peter" \
	 *                   --last_name="Muster" \
	 *                   --email="peter.muster@example.com" \
	 *                   --admin_email="admin@example.com"
	 *
	 * @when after_wp_load
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function person( $args, $assoc_args ) {
		$this->plan        = $this->extract_plan( $assoc_args );
		$this->lang        = $this->extract_lang( $assoc_args );
		$this->first_name  = ucfirst( $this->extract( $assoc_args, 'first_name' ) );
		$this->last_name   = ucfirst( $this->extract( $assoc_args, 'last_name' ) );
		$this->email       = $this->extract_email( $assoc_args, 'email' );
		$this->admin_email = $this->extract_email( $assoc_args, 'admin_email' );

		if ( self::PLAN_ALL_INVLUSIVE === $this->plan ) {
			$this->city       = $this->extract( $assoc_args, 'city' );
			$this->blog_desc  = $this->extract( $assoc_args, 'blog_description' );
			$this->party_name = $this->extract( $assoc_args, 'party_name' );
			$this->party_url  = $this->extract_url( $assoc_args, 'party_url' );
			$this->fb_url     = $this->extract_url( $assoc_args, 'facebook_url', false );
			$this->tw_name    = $this->extract_twitter_name( $assoc_args, 'twitter_name', false );
			$this->insta_url  = $this->extract_url( $assoc_args, 'instagram_url', false );
		}

		$site_id = 'de' === $this->lang ? self::PERSON_SITE_ID_DE : self::PERSON_SITE_ID_FR;

		$this->clone_site( $site_id );
		$this->create_user();
		$this->set_admin_email();
		$this->delete_offer_pages();
		$this->activate_maintenance_mode();

		if ( self::PLAN_ALL_INVLUSIVE === $this->plan ) {
			$this->set_blog_desc();
			$this->set_campaign_headlines();
			$this->set_campaign_cta_desc();
			$this->set_footer_home_party();
			$this->set_social_media_links();
			$this->set_footer_address();
			$this->search_replace_full_name();
			$this->search_replace_first_name();
			$this->search_replace_email();
		}

		$this->clear_cache();

		$this->show_onboarding_mail();
	}

	/**
	 * Clone example site and pre fill it with default content for a party
	 *
	 * @when after_wp_load
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function party() {
		WP_CLI::error( "Not yet implemented" );
	}

	private function extract_plan( $args, $required = true ) {
		$plan = $this->extract( $args, 'plan', $required );
		if ( $plan && ! in_array( $plan, [ self::PLAN_ALL_INVLUSIVE, self::PLAN_MINIMAL ] ) ) {
			WP_CLI::error( "Invalid value for --plan: '$plan'. Allowed values are '" . self::PLAN_ALL_INVLUSIVE . "' and '" . self::PLAN_MINIMAL . "'." );
		}

		return $plan;
	}

	/**
	 * @param $args
	 * @param bool $required
	 *
	 * @return string
	 * @throws WP_CLI\ExitException
	 */
	private function extract_lang( $args, $required = true ) {
		$lang = strtolower( $this->extract( $args, 'lang', $required ) );
		if ( $lang && ! in_array( $lang, [ 'de', 'fr' ] ) ) {
			WP_CLI::error( "Invalid value for --lang: '$lang'. Allowed values are 'de' and 'fr'." );
		}

		return $lang;
	}

	/**
	 * @param $args
	 * @param $key
	 * @param bool $required
	 *
	 * @return string
	 * @throws WP_CLI\ExitException
	 */
	private function extract_email( $args, $key, $required = true ) {
		$email = strtolower( $this->extract( $args, $key, $required ) );
		if ( $email && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			WP_CLI::error( "Invalid $key: $email" );
		}

		return $email;
	}

	/**
	 * @param $args
	 * @param $key
	 * @param bool $required
	 *
	 * @return string
	 * @throws WP_CLI\ExitException
	 */
	private function extract_url( $args, $key, $required = true ) {
		$url = $this->extract( $args, $key, $required );
		if ( $url && ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			WP_CLI::error( "Invalid url: $url" );
		}

		return $url;
	}

	/**
	 * Return value of $key in $args or fail with error message if missing
	 *
	 * @param array $args
	 * @param string $key
	 * @param bool $required key must be present
	 *
	 * @return string
	 * @throws WP_CLI\ExitException
	 */
	private function extract( $args, $key, $required = true ) {
		if ( ! $required && ! array_key_exists( $key, $args ) ) {
			return '';
		}

		if ( ! array_key_exists( $key, $args ) ) {
			WP_CLI::error( "Missing argument: --$key" );
		}

		return trim( $args[ $key ] );
	}

	/**
	 * Extracts the twitter name, even if the twitter url was given.
	 *
	 * @param $args
	 * @param $key
	 * @param bool $required
	 *
	 * @return string
	 */
	private function extract_twitter_name( $args, $key, $required = true ) {
		$url = $this->extract( $args, $key, $required );

		$domain_regex   = '/^(https?:\/\/)?(www\.)?twitter\.com\//';
		$without_domain = preg_replace( $domain_regex, '', $url );

		$sufix_regex = '/\/.*$/';
		$plain_name  = preg_replace( $sufix_regex, '', $without_domain );

		return $plain_name;
	}

	/**
	 * @param $source_site_id
	 *
	 * @throws WP_CLI\ExitException
	 */
	private function clone_site( $source_site_id ) {
		$slug  = sanitize_title( $this->first_name . $this->last_name );
		$title = $this->first_name . ' ' . $this->last_name;

		if ( ! $this->site_exists( $slug ) ) {
			$cloner_url = network_admin_url( 'admin.php?page=ns-cloner', 'https' );
			WP_CLI::log( "--> Manual action required: Duplicate site." );
			WP_CLI::log( "--> Visit $cloner_url" );
			WP_CLI::log( "--> Clone site using the following parameters:" );
			WP_CLI::log( "-->    Mode: Standard Clone" );
			WP_CLI::log( "-->    Source ID: $source_site_id" );
			WP_CLI::log( "-->    New Site Title: $title" );
			WP_CLI::log( "-->    New Site URL: $slug" );

			WP_CLI::confirm( "--> Duplication completed?" );
		}

		$this->site_url = network_site_url($slug, 'https');

		if ( ! $this->site_exists( $slug ) ) {
			WP_CLI::error( "Site not found: {$this->site_url}" );
		}

		WP_CLI::debug( "Site found: {$this->site_url}" );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	private function create_user() {
		$this->user_name = str_replace( '-', '', sanitize_title( $this->first_name . $this->last_name ) );
		$full_name       = $this->first_name . ' ' . $this->last_name;

		$command = sprintf( '--url=%s user create %s %s --role=administrator --display_name=%s ' .
		                    '--user_nicename=%s --first_name=%s --last_name=%s',
			escapeshellarg( $this->site_url ),
			$this->user_name,
			escapeshellarg( $this->email ),
			escapeshellarg( $full_name ),
			escapeshellarg( $full_name ),
			escapeshellarg( $this->first_name ),
			escapeshellarg( $this->last_name )
		);

		$user = $this->run_cli_command( $command );

		if ( preg_match( '/Password: ([^\s]+)/', $user, $matches ) ) {
			$this->password = $matches[1];
			WP_CLI::log( $user );
		} else {
			WP_CLI::error( "Unable create user: $user" );
		}
	}

	private function set_blog_desc() {
		$this->update_option( 'blogdescription', $this->blog_desc );
	}

	private function set_admin_email() {
		$this->update_option( 'admin_email', $this->admin_email );
	}

	private function delete_offer_pages() {
		foreach ( $this->person_offer_site_ids as $id ) {
			$this->delete_page( $id );
		}
	}

	private function delete_page( $id ) {
		$command = sprintf( '--url=%s post delete %d --force',
			escapeshellarg( $this->site_url ),
			$id
		);
		$post    = $this->run_cli_command( $command );

		WP_CLI::log( $post );
	}

	private function set_campaign_headlines() {
		$full_name = $this->first_name . ' ' . $this->last_name;

		$command = sprintf( '--url=%s post meta set %d campaign_bars_headlines_green_0_bar %s',
			escapeshellarg( $this->site_url ),
			$this->person_front_page_id,
			escapeshellarg( $full_name )
		);

		$post = $this->run_cli_command( $command );

		WP_CLI::log( $post );
	}

	private function set_campaign_cta_desc() {
		$content = str_replace( '{{first_name}}', $this->first_name, $this->{'person_campaign_cta_' . $this->lang} );

		$command = sprintf( '--url=%s post meta set %d campaign_call_to_action_description %s',
			escapeshellarg( $this->site_url ),
			$this->person_front_page_id,
			escapeshellarg( $content )
		);

		$post = $this->run_cli_command( $command );

		WP_CLI::log( $post );
	}

	private function set_footer_home_party() {
		$this->update_option( 'widget_supt_link_list_widget-2_list_1_label', $this->party_name );
		$this->update_option( 'widget_supt_link_list_widget-2_list_1_link', $this->party_url );
	}

	private function set_footer_address() {
		$replacements = [
			'{{full_name}}'  => $this->first_name . ' ' . $this->last_name,
			'{{city}}'       => $this->city,
			'{{email}}'      => $this->email,
			'{{send_email}}' => $this->{'send_email_' . $this->lang},
		];

		$content = str_replace( array_keys( $replacements ), array_values( $replacements ), $this->person_address );

		$this->update_option( 'widget_supt_contact_widget-2_address', $content );
	}

	private function set_social_media_links() {
		$i = 0;

		if ( $this->fb_url ) {
			$this->update_option( "widget_supt_contact_widget-2_social_media_{$i}_link", $this->fb_url );
			$this->patch_option_insert( 'widget_supt_contact_widget-2_social_media', 'facebook', null, $i );
			$this->patch_option_insert( 'wpseo_social', $this->fb_url, null, 'facebook_site' );
			$i ++;
		} else {
			$this->patch_option_delete( 'widget_supt_contact_widget-2_social_media', $i );
		}

		if ( $this->tw_name ) {
			$this->update_option( "widget_supt_contact_widget-2_social_media_{$i}_link", 'https://twitter.com/' . $this->tw_name );
			$this->patch_option_insert( 'widget_supt_contact_widget-2_social_media', 'twitter', null, $i );
			$this->patch_option_insert( 'wpseo_social', $this->tw_name, null, 'twitter_site' );
			$i ++;
		} else {
			$this->patch_option_delete( 'widget_supt_contact_widget-2_social_media', $i );
		}

		if ( $this->insta_url ) {
			$this->update_option( "widget_supt_contact_widget-2_social_media_{$i}_link", $this->insta_url );
			$this->patch_option_insert( 'widget_supt_contact_widget-2_social_media', 'instagram', null, $i );
			$this->patch_option_insert( 'wpseo_social', $this->insta_url, null, 'instagram_url' );
		} else {
			$this->patch_option_delete( 'widget_supt_contact_widget-2_social_media', $i );
		}

		for ( $j = 2; $j > $i; $j -- ) {
			$this->patch_option_delete( 'widget_supt_contact_widget-2_social_media', $j );
		}
	}

	/**
	 * Update an option field
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $format as it is entered in the cli. eg: --format=json
	 */
	private function update_option( $key, $value, $format = '' ) {
		if ( $format ) {
			$format .= ' ';
		}

		$command = sprintf( '--url=%s option update %s %s %s',
			escapeshellarg( $this->site_url ),
			$format,
			$key,
			escapeshellarg( $value )
		);

		$option = $this->run_cli_command( $command );

		WP_CLI::log( $option );
	}

	/**
	 * Upsert a nested option field
	 *
	 * @param string $key
	 * @param string $value
	 * @param string|null|false $format as it is entered in the cli. eg: --format=json
	 * @param string ...$path the sub key path to the value
	 */
	private function patch_option_insert( $key, $value, $format, ...$path ) {
		if ( $format ) {
			$format .= ' ';
		}

		$command = sprintf( '--url=%s option patch insert %s %s %s %s',
			escapeshellarg( $this->site_url ),
			$key,
			implode( ' ', $path ),
			escapeshellarg( $value ),
			$format
		);

		$option = $this->run_cli_command( $command );

		WP_CLI::log( $option );
	}

	/**
	 * Delete a nested option field
	 *
	 * @param string $key
	 * @param string ...$path the sub key path to the value
	 */
	private function patch_option_delete( $key, ...$path ) {
		$command = sprintf( '--url=%s option patch delete %s %s',
			escapeshellarg( $this->site_url ),
			$key,
			implode( ' ', $path )
		);

		$option = $this->run_cli_command( $command );

		WP_CLI::log( $option );
	}

	private function search_replace_full_name() {
		$command = sprintf( '--url=%s search-replace %s %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"name_{$this->lang}"} ),
			escapeshellarg( $this->first_name . ' ' . $this->last_name )
		);

		$replace = $this->run_cli_command( $command );

		WP_CLI::log( $replace );
	}

	private function search_replace_first_name() {
		$command = sprintf( '--url=%s search-replace %s %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"first_name_{$this->lang}"} ),
			escapeshellarg( $this->first_name )
		);

		$replace = $this->run_cli_command( $command );

		WP_CLI::log( $replace );
	}

	private function search_replace_email() {
		$command = sprintf( '--url=%s search-replace %s %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"email_{$this->lang}"} ),
			escapeshellarg( $this->email )
		);

		$replace = $this->run_cli_command( $command );

		WP_CLI::log( $replace );
	}

	private function activate_maintenance_mode() {
		// activate plugin
		$command = sprintf( '--url=%s plugin activate underconstruction',
			escapeshellarg( $this->site_url )
		);

		$this->run_cli_command( $command );

		// enable maintenance mode
		$command = sprintf( '--url=%s option set underConstructionActivationStatus 1',
			escapeshellarg( $this->site_url )
		);

		$this->run_cli_command( $command );

		// set title
		$command = sprintf( '--url=%s option patch update underConstructionCustomText pageTitle %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"maintenance_message_{$this->lang}"} )
		);

		$this->run_cli_command( $command );

		// set message title
		$command = sprintf( '--url=%s option patch update underConstructionCustomText headerText %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"maintenance_message_{$this->lang}"} )
		);

		$this->run_cli_command( $command );

		// set message body
		$command = sprintf( '--url=%s option patch update underConstructionCustomText bodyText ""',
			escapeshellarg( $this->site_url )
		);

		$this->run_cli_command( $command );

		// set mode
		$command = sprintf( '--url=%s option patch set underConstructionDisplayOption 1',
			escapeshellarg( $this->site_url )
		);

		$this->run_cli_command( $command );
	}

	/**
	 * Log and run cli command
	 *
	 * @param string $command
	 *
	 * @return mixed
	 */
	private function run_cli_command( $command ) {
		WP_CLI::debug( 'Running Command: wp ' . $command );

		// don't strip umlauts
		setlocale( LC_CTYPE, "de_CH.UTF-8" );

		// run the command directly with shell_exec because WP_CLI::runcommand is buggy if you need quoted associated
		// arguments and WP_CLI::run_command doesn't let you capture the output
		$out = shell_exec( 'wp ' . $command );

		return $out;
	}

	private function show_onboarding_mail() {
		$links = <<<EOL
Link: {$this->site_url}
Login: {$this->site_url}/wp-admin
EOL;

		$raw_message  = $this->{"mail_{$this->lang}"};
		$replacements = [
			'{{first_name}}'  => $this->first_name,
			'{{links}}' => $links,
			'{{email}}' => $this->email,
			'{{support}}'     => $this->{"support_{$this->plan}_{$this->lang}"}
		];

		$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw_message );

		WP_CLI::success( "{$this->first_name} {$this->last_name} onboarded. ".
		                 "Send {$this->first_name} ({$this->email}) the following mail: " );

		$lines = explode( "\n", $message );
		foreach ( $lines as $line ) {
			WP_CLI::line( $line );
		}
	}

	private function site_exists( string $slug ): bool {
		$sites     = $this->run_cli_command( "site list --field=url" );
		$url       = rtrim( network_site_url( $slug, 'https' ), '/' );
		$url_regex = preg_quote( $url, '/' );

		return preg_match( "/^$url_regex\/?$/m", $sites );
	}

	private function clear_cache(): void {
		$command = sprintf( '--url=%s cache flush',
			escapeshellarg( $this->site_url )
		);

		$this->run_cli_command( $command );
	}
}

