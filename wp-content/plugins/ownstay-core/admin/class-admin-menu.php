<?php
/**
 * OwnStay WordPress Admin Menu Architecture.
 *
 * Registers the complete OwnStay admin navigation structure in WordPress backend.
 * Only accessible to internal staff/administrators with respective RBAC capabilities.
 *
 * @package OwnStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OwnStay_Admin_Menu Class.
 */
class OwnStay_Admin_Menu {

	/**
	 * Singleton instance.
	 *
	 * @var OwnStay_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return OwnStay_Admin_Menu
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Enqueue minimal styling for OwnStay admin placeholder screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( false === strpos( $hook, 'ownstay' ) ) {
			return;
		}

		wp_register_style( 'ownstay-admin-style', false, array(), OWNSTAY_CORE_VERSION );
		wp_enqueue_style( 'ownstay-admin-style' );
		wp_add_inline_style(
			'ownstay-admin-style',
			'
			.ownstay-admin-wrap {
				max-width: 1100px;
				margin: 24px 20px 24px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.ownstay-admin-card {
				background: #ffffff;
				border: 1px solid #e5e5e7;
				border-radius: 16px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.04);
				padding: 32px;
				margin-bottom: 24px;
			}
			.ownstay-admin-header {
				display: flex;
				align-items: center;
				gap: 16px;
				margin-bottom: 20px;
			}
			.ownstay-admin-icon {
				width: 48px;
				height: 48px;
				border-radius: 12px;
				background: #1d1d1f;
				color: #ffffff;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 24px;
			}
			.ownstay-admin-title h1 {
				font-size: 24px;
				font-weight: 700;
				color: #1d1d1f;
				margin: 0 0 4px 0;
				line-height: 1.2;
			}
			.ownstay-admin-title p {
				font-size: 13px;
				color: #86868b;
				margin: 0;
			}
			.ownstay-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				background: #f5f5f7;
				color: #1d1d1f;
				margin-bottom: 12px;
				border: 1px solid #ededed;
			}
			.ownstay-admin-body {
				padding: 24px;
				background: #fbfbfd;
				border-radius: 12px;
				border: 1px dashed #d2d2d7;
				text-align: center;
				margin-top: 16px;
			}
			.ownstay-admin-body h3 {
				margin: 0 0 8px 0;
				font-size: 16px;
				font-weight: 600;
				color: #1d1d1f;
			}
			.ownstay-admin-body p {
				color: #6e6e73;
				font-size: 13px;
				margin: 0 auto;
				max-width: 520px;
				line-height: 1.5;
			}
			.ownstay-admin-meta {
				display: flex;
				flex-wrap: wrap;
				gap: 12px;
				margin-top: 20px;
				justify-content: center;
			}
			.ownstay-meta-pill {
				background: #ffffff;
				border: 1px solid #e5e5e7;
				padding: 6px 12px;
				border-radius: 8px;
				font-size: 12px;
				color: #1d1d1f;
				font-weight: 500;
			}
			.ownstay-status-badge {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 12px;
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
			}
			.ownstay-status-verified { background: #dcfce7; color: #166534; }
			.ownstay-status-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
			.ownstay-status-unverified { background: #f3f4f6; color: #4b5563; }
			.ownstay-status-rejected { background: #fee2e2; color: #991b1b; }
			.ownstay-status-suspended { background: #450a0a; color: #fef2f2; }
			.ownstay-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
			.ownstay-table th { background: #fbfbfd; text-align: left; padding: 12px; font-size: 12px; color: #86868b; border-bottom: 1px solid #e5e5e7; }
			.ownstay-table td { padding: 14px 12px; border-bottom: 1px solid #ededed; font-size: 13px; color: #1d1d1f; vertical-align: middle; }
			.ownstay-btn { padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
			.ownstay-btn-approve { background: #166534; color: #ffffff; }
			.ownstay-btn-approve:hover { background: #14532d; }
			.ownstay-btn-reject { background: #991b1b; color: #ffffff; }
			.ownstay-btn-reject:hover { background: #7f1d1d; }
			.ownstay-btn-secondary { background: #f5f5f7; color: #1d1d1f; border: 1px solid #d2d2d7; }
			'
		);
	}

	/**
	 * Register the complete OwnStay menu hierarchy.
	 */
	public function register_admin_menu() {
		// Parent Menu: OwnStay.
		add_menu_page(
			__( 'OwnStay Platform Admin', 'ownstay-core' ),
			__( 'OwnStay', 'ownstay-core' ),
			'ownstay_manage_properties',
			'ownstay-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-building',
			26
		);

		// 1. Dashboard (First Submenu Item).
		add_submenu_page(
			'ownstay-dashboard',
			__( 'OwnStay Dashboard', 'ownstay-core' ),
			__( 'Dashboard', 'ownstay-core' ),
			'ownstay_manage_properties',
			'ownstay-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// 2. Properties.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Property Listings', 'ownstay-core' ),
			__( 'Properties', 'ownstay-core' ),
			'ownstay_manage_properties',
			'ownstay-properties',
			array( $this, 'render_properties_page' )
		);

		// 3. Property Verification.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Property Verification & Inspection', 'ownstay-core' ),
			__( 'Property Verification', 'ownstay-core' ),
			'ownstay_verify_property',
			'ownstay-property-verification',
			array( $this, 'render_property_verification_page' )
		);

		// 4. Owners.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Property Owners', 'ownstay-core' ),
			__( 'Owners', 'ownstay-core' ),
			'ownstay_manage_users',
			'ownstay-owners',
			array( $this, 'render_owners_page' )
		);

		// 5. Owner Verification.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Owner KYC & Aadhaar/PAN Verification', 'ownstay-core' ),
			__( 'Owner Verification', 'ownstay-core' ),
			'ownstay_verify_owner',
			'ownstay-owner-verification',
			array( $this, 'render_owner_verification_page' )
		);

		// 6. Tenants.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Verified Tenants', 'ownstay-core' ),
			__( 'Tenants', 'ownstay-core' ),
			'ownstay_manage_users',
			'ownstay-tenants',
			array( $this, 'render_tenants_page' )
		);

		// 7. Visits.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'NFC Smart-Lock Tours & Visits', 'ownstay-core' ),
			__( 'Visits', 'ownstay-core' ),
			'ownstay_manage_properties',
			'ownstay-visits',
			array( $this, 'render_visits_page' )
		);

		// 8. Bookings.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Rental Bookings & Agreements', 'ownstay-core' ),
			__( 'Bookings', 'ownstay-core' ),
			'ownstay_manage_properties',
			'ownstay-bookings',
			array( $this, 'render_bookings_page' )
		);

		// 9. Payments.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'UPI Autopay & Zero-Brokerage Transactions', 'ownstay-core' ),
			__( 'Payments', 'ownstay-core' ),
			'ownstay_manage_payments',
			'ownstay-payments',
			array( $this, 'render_payments_page' )
		);

		// 10. Complaints.
		add_submenu_page(
			'ownstay-dashboard',
			__( '24/7 Support & Dispute Resolution', 'ownstay-core' ),
			__( 'Complaints', 'ownstay-core' ),
			'ownstay_manage_complaints',
			'ownstay-complaints',
			array( $this, 'render_complaints_page' )
		);

		// 11. Reviews.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Tenant & Landlord Reviews', 'ownstay-core' ),
			__( 'Reviews', 'ownstay-core' ),
			'ownstay_moderate_reviews',
			'ownstay-reviews',
			array( $this, 'render_reviews_page' )
		);

		// 12. Analytics.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Tier-2 Market Intelligence & Analytics', 'ownstay-core' ),
			__( 'Analytics', 'ownstay-core' ),
			'ownstay_view_analytics',
			'ownstay-analytics',
			array( $this, 'render_analytics_page' )
		);

		// 13. Settings.
		add_submenu_page(
			'ownstay-dashboard',
			__( 'Platform Settings & RBAC Policy', 'ownstay-core' ),
			__( 'Settings', 'ownstay-core' ),
			'manage_options',
			'ownstay-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render a uniform, professional Apple-aesthetic admin screen.
	 *
	 * @param string $title Page title.
	 * @param string $description Page description.
	 * @param string $badge Badge text.
	 * @param string $icon_dashicon Dashicon class name.
	 * @param string $required_cap Authoritative capability required.
	 */
	private function render_admin_placeholder( $title, $description, $badge, $icon_dashicon, $required_cap ) {
		?>
		<div class="wrap ownstay-admin-wrap">
			<div class="ownstay-admin-card">
				<div class="ownstay-badge"><?php echo esc_html( $badge ); ?></div>
				<div class="ownstay-admin-header">
					<div class="ownstay-admin-icon">
						<span class="dashicons <?php echo esc_attr( $icon_dashicon ); ?>"></span>
					</div>
					<div class="ownstay-admin-title">
						<h1><?php echo esc_html( $title ); ?></h1>
						<p><?php echo esc_html( $description ); ?></p>
					</div>
				</div>

				<div class="ownstay-admin-body">
					<h3><?php echo esc_html( $title ); ?> — Admin Architecture Registered</h3>
					<p>
						<?php esc_html_e( 'The administrative menu architecture and RBAC capability boundary are active. This interface is restricted to authorized internal staff and administrators.', 'ownstay-core' ); ?>
					</p>
					<div class="ownstay-admin-meta">
						<span class="ownstay-meta-pill">
							<strong><?php esc_html_e( 'Required Capability:', 'ownstay-core' ); ?></strong>
							<code><?php echo esc_html( $required_cap ); ?></code>
						</span>
						<span class="ownstay-meta-pill">
							<strong><?php esc_html_e( 'Headless Frontend:', 'ownstay-core' ); ?></strong>
							Next.js API V1
						</span>
						<span class="ownstay-meta-pill">
							<strong><?php esc_html_e( 'Status:', 'ownstay-core' ); ?></strong>
							<?php esc_html_e( 'Architecture Ready', 'ownstay-core' ); ?>
						</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 1. Render Dashboard Page.
	 */
	public function render_dashboard_page() {
		$this->render_admin_placeholder(
			__( 'OwnStay Platform Dashboard', 'ownstay-core' ),
			__( 'Centralized administration for Indian Tier-2 verified long-term rentals.', 'ownstay-core' ),
			__( 'Platform Command Center', 'ownstay-core' ),
			'dashicons-chart-pie',
			'ownstay_manage_properties'
		);
	}

	/**
	 * 2. Render Properties Page.
	 */
	public function render_properties_page() {
		$this->render_admin_placeholder(
			__( 'Property Listings Management', 'ownstay-core' ),
			__( 'Review, audit, and moderate residential properties across Tier-2 Indian hubs.', 'ownstay-core' ),
			__( 'Inventory Administration', 'ownstay-core' ),
			'dashicons-building',
			'ownstay_manage_properties'
		);
	}

	/**
	 * 3. Render Property Verification Page.
	 */
	public function render_property_verification_page() {
		$this->render_admin_placeholder(
			__( 'Property Verification & Physical Inspection', 'ownstay-core' ),
			__( 'Verify title deeds, conduct physical inspections, and authorize NFC smart-lock installation.', 'ownstay-core' ),
			__( 'Verification & Inspection', 'ownstay-core' ),
			'dashicons-saved',
			'ownstay_verify_property'
		);
	}

	/**
	 * 4. Render Owners Page.
	 */
	public function render_owners_page() {
		$this->render_admin_placeholder(
			__( 'Property Owners Directory', 'ownstay-core' ),
			__( 'Manage registered landlord accounts and monitor portfolio compliance.', 'ownstay-core' ),
			__( 'User Management', 'ownstay-core' ),
			'dashicons-businessman',
			'ownstay_manage_users'
		);
	}

	/**
	 * 5. Render Owner Verification Page.
	 * Complete administrative review console for KYC & Aadhaar/PAN Verification.
	 */
	public function render_owner_verification_page() {
		if ( ! current_user_can( 'ownstay_verify_owner' ) && ! current_user_can( 'administrator' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ownstay-core' ) );
		}

		// Handle Form Actions (Approve / Reject / Reset).
		if ( isset( $_POST['ownstay_action'] ) && 'update_owner_verification' === $_POST['ownstay_action'] ) {
			check_admin_referer( 'ownstay_update_owner_verification_nonce' );

			$target_user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$new_status     = isset( $_POST['new_status'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) ) ) : '';

			$allowed_statuses = array( 'unverified', 'pending', 'verified', 'rejected', 'suspended' );
			if ( $target_user_id && in_array( $new_status, $allowed_statuses, true ) ) {
				update_user_meta( $target_user_id, 'owner_verification_status', $new_status );
				update_user_meta( $target_user_id, 'ownstay_verification_status', $new_status );

				echo '<div class="notice notice-success is-dismissible"><p>' .
					sprintf(
						/* translators: 1: User ID, 2: Verification Status */
						esc_html__( 'Owner #%1$d KYC verification status updated to: %2$s', 'ownstay-core' ),
						(int) $target_user_id,
						'<strong>' . esc_html( strtoupper( $new_status ) ) . '</strong>'
					) .
					'</p></div>';
			}
		}

		// Query all users who are ownstay_owner or have an owner_verification_status.
		$owners = get_users(
			array(
				'role__in' => array( 'ownstay_owner', 'administrator' ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);
		?>
		<div class="wrap ownstay-admin-wrap">
			<div class="ownstay-admin-card">
				<div class="ownstay-badge"><?php esc_html_e( 'KYC & Identity Review Desk', 'ownstay-core' ); ?></div>
				<div class="ownstay-admin-header">
					<div class="ownstay-admin-icon">
						<span class="dashicons dashicons-id-alt"></span>
					</div>
					<div class="ownstay-admin-title">
						<h1><?php esc_html_e( 'Owner KYC & Aadhaar/PAN Verification', 'ownstay-core' ); ?></h1>
						<p><?php esc_html_e( 'Review submitted KYC documents, verify Aadhaar/PAN identity, and authorize property publishing privileges.', 'ownstay-core' ); ?></p>
					</div>
				</div>

				<div style="margin-top: 24px;">
					<?php if ( empty( $owners ) ) : ?>
						<p><?php esc_html_e( 'No property owner accounts found.', 'ownstay-core' ); ?></p>
					<?php else : ?>
						<table class="ownstay-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'User ID', 'ownstay-core' ); ?></th>
									<th><?php esc_html_e( 'Owner Name', 'ownstay-core' ); ?></th>
									<th><?php esc_html_e( 'Email', 'ownstay-core' ); ?></th>
									<th><?php esc_html_e( 'Registered Date', 'ownstay-core' ); ?></th>
									<th><?php esc_html_e( 'KYC Verification Status', 'ownstay-core' ); ?></th>
									<th><?php esc_html_e( 'Review Actions', 'ownstay-core' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $owners as $owner_user ) :
									$status = get_user_meta( $owner_user->ID, 'owner_verification_status', true );
									if ( empty( $status ) ) {
										$status = get_user_meta( $owner_user->ID, 'ownstay_verification_status', true );
									}
									if ( empty( $status ) ) {
										$status = 'unverified';
									}
									$status_lower = strtolower( trim( $status ) );

									$name = trim( $owner_user->first_name . ' ' . $owner_user->last_name );
									if ( empty( $name ) ) {
										$name = $owner_user->display_name;
									}
									?>
									<tr>
										<td><strong>#<?php echo (int) $owner_user->ID; ?></strong></td>
										<td>
											<span style="font-weight: 600;"><?php echo esc_html( $name ); ?></span>
											<br><small style="color: #86868b;">Role: <code><?php echo esc_html( reset( $owner_user->roles ) ); ?></code></small>
										</td>
										<td><?php echo esc_html( $owner_user->user_email ); ?></td>
										<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $owner_user->user_registered ) ) ); ?></td>
										<td>
											<span class="ownstay-status-badge ownstay-status-<?php echo esc_attr( $status_lower ); ?>">
												<?php echo esc_html( strtoupper( $status_lower ) ); ?>
											</span>
										</td>
										<td>
											<div style="display: flex; gap: 8px; flex-wrap: wrap;">
												<?php if ( 'verified' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'ownstay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="ownstay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="verified" />
														<button type="submit" class="ownstay-btn ownstay-btn-approve">
															<?php esc_html_e( 'Approve (Verify)', 'ownstay-core' ); ?>
														</button>
													</form>
												<?php endif; ?>

												<?php if ( 'rejected' !== $status_lower && 'unverified' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'ownstay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="ownstay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="rejected" />
														<button type="submit" class="ownstay-btn ownstay-btn-reject">
															<?php esc_html_e( 'Reject', 'ownstay-core' ); ?>
														</button>
													</form>
												<?php endif; ?>

												<?php if ( 'pending' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'ownstay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="ownstay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="pending" />
														<button type="submit" class="ownstay-btn ownstay-btn-secondary">
															<?php esc_html_e( 'Set Pending', 'ownstay-core' ); ?>
														</button>
													</form>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 6. Render Tenants Page.
	 */
	public function render_tenants_page() {
		$this->render_admin_placeholder(
			__( 'Verified Tenants Directory', 'ownstay-core' ),
			__( 'Manage tenant accounts, employment verification records, and rental histories.', 'ownstay-core' ),
			__( 'User Management', 'ownstay-core' ),
			'dashicons-groups',
			'ownstay_manage_users'
		);
	}

	/**
	 * 7. Render Visits Page.
	 */
	public function render_visits_page() {
		$this->render_admin_placeholder(
			__( 'NFC Smart-Lock Tour Logs', 'ownstay-core' ),
			__( 'Monitor self-guided NFC smart-lock entries and scheduled prospective tenant visits.', 'ownstay-core' ),
			__( 'Physical Access Logs', 'ownstay-core' ),
			'dashicons-location',
			'ownstay_manage_properties'
		);
	}

	/**
	 * 8. Render Bookings Page.
	 */
	public function render_bookings_page() {
		$this->render_admin_placeholder(
			__( 'Bookings & Digital Lease Agreements', 'ownstay-core' ),
			__( 'Review active rental applications and manage Aadhaar e-signed digital lease contracts.', 'ownstay-core' ),
			__( 'Lease Administration', 'ownstay-core' ),
			'dashicons-media-document',
			'ownstay_manage_properties'
		);
	}

	/**
	 * 9. Render Payments Page.
	 */
	public function render_payments_page() {
		$this->render_admin_placeholder(
			__( 'Rental Payments & UPI Autopay Ledger', 'ownstay-core' ),
			__( 'Track 100% zero-brokerage rent disbursements, security deposits, and NPCI UPI mandates.', 'ownstay-core' ),
			__( 'Financial Operations', 'ownstay-core' ),
			'dashicons-money-alt',
			'ownstay_manage_payments'
		);
	}

	/**
	 * 10. Render Complaints Page.
	 */
	public function render_complaints_page() {
		$this->render_admin_placeholder(
			__( 'Complaints & Dispute Resolution', 'ownstay-core' ),
			__( 'Manage 24/7 maintenance tickets, tenant-landlord disputes, and resolution workflows.', 'ownstay-core' ),
			__( 'Support Desk', 'ownstay-core' ),
			'dashicons-sos',
			'ownstay_manage_complaints'
		);
	}

	/**
	 * 11. Render Reviews Page.
	 */
	public function render_reviews_page() {
		$this->render_admin_placeholder(
			__( 'Review Moderation & Trust Scores', 'ownstay-core' ),
			__( 'Moderate property reviews, landlord trust ratings, and verified tenant feedback.', 'ownstay-core' ),
			__( 'Content Moderation', 'ownstay-core' ),
			'dashicons-star-filled',
			'ownstay_moderate_reviews'
		);
	}

	/**
	 * 12. Render Analytics Page.
	 */
	public function render_analytics_page() {
		$this->render_admin_placeholder(
			__( 'Tier-2 Long-Term Rental Analytics', 'ownstay-core' ),
			__( 'Market occupancy rates, yield trends across Indore/Jaipur/Surat, and platform metrics.', 'ownstay-core' ),
			__( 'Business Intelligence', 'ownstay-core' ),
			'dashicons-chart-bar',
			'ownstay_view_analytics'
		);
	}

	/**
	 * 13. Render Settings Page.
	 */
	public function render_settings_page() {
		$this->render_admin_placeholder(
			__( 'Platform Configuration & RBAC Policy', 'ownstay-core' ),
			__( 'Configure JWT/cookie authentication tokens, CORS origins, and KYC verification thresholds.', 'ownstay-core' ),
			__( 'System Configuration', 'ownstay-core' ),
			'dashicons-admin-settings',
			'manage_options'
		);
	}
}
