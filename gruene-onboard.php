<?php
/**
 * Plugin Name:     Gruene Onboard
 * Plugin URI:      https://github.com/grueneschweiz/onboard-website-plugin
 * Description:     Automated onboarding of new websites
 * Author:          grueneschweiz
 * Author URI:      https://gruene.ch
 * Text Domain:     gruene-onboard
 * Version:         1.0.0
 *
 * @package         Gruene_Onboard
 */

namespace Gruene_Onboard;

use WP_CLI;
use function sanitize_title;

define( 'COMMAND_NAME', 'onboard' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/** @noinspection PhpUnhandledExceptionInspection */ // die hard
	WP_CLI::add_command( COMMAND_NAME, '\Gruene_Onboard\Onboarder' );
}

/**
 * Holds the CLI commands.
 *
 * Note: The commands doc blocks are parsed by WP, so they must respect the
 * conventions.
 *
 * @see https://make.wordpress.org/cli/handbook/commands-cookbook/
 *
 * @package Gruene_Clone\Commands
 */
class Onboarder {
	const PERSON_SITE_ID_DE = 4;
	const PERSON_SITE_ID_FR = 8;

	private $person_offer_site_ids = [ 653, 624, 648 ];
	private $person_front_page_id = 513;

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$name_de = 'Peter Muster';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$name_fr = 'Anne Modèle';

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$person_campaign_cta_de = 'Darum trete ich dem Unterstützungskomitee bei und zeige mit meinem Namen, dass {{first_name}} eine gute Wahl ist.';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$person_campaign_cta_fr = "J'adhère au comité de soutien et je montre par mon nom que {{first_name}} est un bon choix.";

	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$send_email_de = 'Email senden';
	private /** @noinspection PhpUnusedPrivateFieldInspection */
		$send_email_fr = 'Envoyer un e-mail';

	private $person_address = <<<EOL
<b>{{full_name}}</b>
{{city}}

<a class="a-button a-button--primary" href="mailto:{{email}}">{{send_email}}</a>
EOL;


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
	 * [--lang=<de|fr>]
	 * : The first name of the person
	 *
	 * [--first_name=<first-name>]
	 * : The first name of the person
	 *
	 * [--last_name=<last-name>]
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
	 * wp onboard person --lang=de \
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
	 * @when after_wp_load
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function person( $args, $assoc_args ) {
		$this->lang        = $this->extract_lang( $assoc_args );
		$this->first_name  = ucfirst( $this->extract( $assoc_args, 'first_name' ) );
		$this->last_name   = ucfirst( $this->extract( $assoc_args, 'last_name' ) );
		$this->email       = $this->extract_email( $assoc_args, 'email' );
		$this->city        = $this->extract( $assoc_args, 'city' );
		$this->blog_desc   = $this->extract( $assoc_args, 'blog_description' );
		$this->party_name  = $this->extract( $assoc_args, 'party_name' );
		$this->party_url   = $this->extract_url( $assoc_args, 'party_url' );
		$this->fb_url      = $this->extract_url( $assoc_args, 'facebook_url', false );
		$this->tw_name     = $this->extract( $assoc_args, 'twitter_name', false );
		$this->insta_url   = $this->extract_url( $assoc_args, 'instagram_url', false );
		$this->admin_email = $this->extract_email( $assoc_args, 'admin_email' );

		$site_id = 'de' === $this->lang ? self::PERSON_SITE_ID_DE : self::PERSON_SITE_ID_FR;

		$this->clone_site( $site_id );
		$this->create_user();
		$this->set_blog_desc();
		$this->set_admin_email();
		$this->set_campaign_headlines();
		$this->set_campaign_cta_desc();
		$this->set_footer_home_party();
		$this->set_social_media_links();
		$this->delete_offer_pages();
		$this->set_footer_address();
		$this->search_replace_name();

		WP_CLI::success( "{$this->first_name} {$this->last_name} onboarded." );
		WP_CLI::line( "URL: {$this->site_url}" );
		WP_CLI::line( "Admin URL: {$this->site_url}wp-admin" );
		WP_CLI::line( "Username: {$this->user_name}" );
		WP_CLI::line( "Password: {$this->password}" );
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
			WP_CLI::error( "Invalid language key: $lang" );
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
	 * @param $source_site_id
	 *
	 * @throws WP_CLI\ExitException
	 */
	private function clone_site( $source_site_id ) {
		$slug  = sanitize_title( $this->first_name . $this->last_name );
		$title = $this->first_name . ' ' . $this->last_name;

		$command = sprintf(
			'site duplicate --slug=%s --title=%s --source=%d',
			$slug,
			escapeshellarg( $title ),
			$source_site_id
		);
		$clone   = $this->run_cli_command( $command );

		if ( preg_match( '/https?:\/\/[^\s]+/', $clone, $matches ) ) {
			$this->site_url = $matches[0];
			WP_CLI::log( $clone );
		} else {
			WP_CLI::error( "Unable to parse url from site cloner output: $clone" );
		}
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
			$this->patch_option( 'update', 'widget_supt_contact_widget-2_social_media', 'facebook', null, $i );
			$this->patch_option( 'update', 'wpseo_social', $this->fb_url, null, 'facebook_site' );
			$i ++;
		} else {
			$this->patch_option( 'delete', 'widget_supt_contact_widget-2_social_media', '', null, 0 );
		}

		if ( $this->tw_name ) {
			$this->update_option( "widget_supt_contact_widget-2_social_media_{$i}_link", 'https://twitter.com/' . $this->tw_name );
			$this->patch_option( 'update', 'widget_supt_contact_widget-2_social_media', 'twitter', null, $i );
			$this->patch_option( 'update', 'wpseo_social', $this->tw_name, null, 'twitter_site' );
			$i ++;
		} else {
			$this->patch_option( 'delete', 'widget_supt_contact_widget-2_social_media', '', null, 1 );
		}

		if ( $this->insta_url ) {
			$this->update_option( "widget_supt_contact_widget-2_social_media_{$i}_link", $this->insta_url );
			$this->patch_option( 'update', 'widget_supt_contact_widget-2_social_media', 'instagram', null, $i );
			$this->patch_option( 'update', 'wpseo_social', $this->insta_url, null, 'instagram_url' );
		} else {
			$this->patch_option( 'delete', 'widget_supt_contact_widget-2_social_media', '', null, 2 );
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
	 * Update an option field
	 *
	 * @param string $mode
	 * @param string $key
	 * @param string $value
	 * @param string|null|false $format as it is entered in the cli. eg: --format=json
	 * @param string ...$path the sub key path to the value
	 */
	private function patch_option( $mode, $key, $value, $format, ...$path ) {
		if ( $format ) {
			$format .= ' ';
		}

		$command = sprintf( '--url=%s option patch %s %s %s %s %s',
			escapeshellarg( $this->site_url ),
			$mode,
			$key,
			implode( ' ', $path ),
			escapeshellarg( $value ),
			$format
		);

		$option = $this->run_cli_command( $command );

		WP_CLI::log( $option );
	}

	private function search_replace_name() {
		$command = sprintf( '--url=%s search-replace %s %s',
			escapeshellarg( $this->site_url ),
			escapeshellarg( $this->{"name_{$this->lang}"} ),
			escapeshellarg( $this->first_name . ' ' . $this->last_name )
		);

		$replace = $this->run_cli_command( $command );

		WP_CLI::log( $replace );
	}

	/**
	 * Log and run cli command
	 *
	 * @param string $command
	 *
	 * @return mixed
	 */
	private function run_cli_command( $command ) {
		WP_CLI::line( 'Running Command: wp ' . $command );

		// run the command directly with shell_exec because WP_CLI::runcommand is buggy if you need quoted associated
		// arguments and WP_CLI::run_command doesn't let you capture the output
		$out = shell_exec( 'wp ' . $command );

		return $out;
	}
}