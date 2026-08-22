<?php
/**
 * ApnaStay WordPress Admin Menu Architecture.
 *
 * Registers the complete ApnaStay admin navigation structure in WordPress backend.
 * Only accessible to internal staff/administrators with respective RBAC capabilities.
 *
 * @package ApnaStay_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Admin_Menu Class.
 */
class ApnaStay_Admin_Menu {

	/**
	 * Singleton instance.
	 *
	 * @var ApnaStay_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ApnaStay_Admin_Menu
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
	 * Enqueue minimal styling for ApnaStay admin placeholder screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( false === strpos( $hook, 'apnastay' ) ) {
			return;
		}

		wp_register_style( 'apnastay-admin-style', false, array(), APNASTAY_CORE_VERSION );
		wp_enqueue_style( 'apnastay-admin-style' );
		wp_add_inline_style(
			'apnastay-admin-style',
			'
			.apnastay-admin-wrap {
				max-width: 1100px;
				margin: 24px 20px 24px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.apnastay-admin-card {
				background: #ffffff;
				border: 1px solid #e5e5e7;
				border-radius: 16px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.04);
				padding: 32px;
				margin-bottom: 24px;
			}
			.apnastay-admin-header {
				display: flex;
				align-items: center;
				gap: 16px;
				margin-bottom: 20px;
			}
			.apnastay-admin-icon {
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
			.apnastay-admin-title h1 {
				font-size: 24px;
				font-weight: 700;
				color: #1d1d1f;
				margin: 0 0 4px 0;
				line-height: 1.2;
			}
			.apnastay-admin-title p {
				font-size: 13px;
				color: #86868b;
				margin: 0;
			}
			.apnastay-badge {
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
			.apnastay-admin-body {
				padding: 24px;
				background: #fbfbfd;
				border-radius: 12px;
				border: 1px dashed #d2d2d7;
				text-align: center;
				margin-top: 16px;
			}
			.apnastay-admin-body h3 {
				margin: 0 0 8px 0;
				font-size: 16px;
				font-weight: 600;
				color: #1d1d1f;
			}
			.apnastay-admin-body p {
				color: #6e6e73;
				font-size: 13px;
				margin: 0 auto;
				max-width: 520px;
				line-height: 1.5;
			}
			.apnastay-admin-meta {
				display: flex;
				flex-wrap: wrap;
				gap: 12px;
				margin-top: 20px;
				justify-content: center;
			}
			.apnastay-meta-pill {
				background: #ffffff;
				border: 1px solid #e5e5e7;
				padding: 6px 12px;
				border-radius: 8px;
				font-size: 12px;
				color: #1d1d1f;
				font-weight: 500;
			}
			.apnastay-status-badge {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 12px;
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
			}
			.apnastay-status-verified { background: #dcfce7; color: #166534; }
			.apnastay-status-pending { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
			.apnastay-status-unverified { background: #f3f4f6; color: #4b5563; }
			.apnastay-status-rejected { background: #fee2e2; color: #991b1b; }
			.apnastay-status-suspended { background: #450a0a; color: #fef2f2; }
			.apnastay-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
			.apnastay-table th { background: #fbfbfd; text-align: left; padding: 12px; font-size: 12px; color: #86868b; border-bottom: 1px solid #e5e5e7; }
			.apnastay-table td { padding: 14px 12px; border-bottom: 1px solid #ededed; font-size: 13px; color: #1d1d1f; vertical-align: middle; }
			.apnastay-btn { padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
			.apnastay-btn-approve { background: #166534; color: #ffffff; }
			.apnastay-btn-approve:hover { background: #14532d; }
			.apnastay-btn-reject { background: #991b1b; color: #ffffff; }
			.apnastay-btn-reject:hover { background: #7f1d1d; }
			.apnastay-btn-secondary { background: #f5f5f7; color: #1d1d1f; border: 1px solid #d2d2d7; }
			'
		);
	}

	/**
	 * Register the complete ApnaStay menu hierarchy.
	 */
	public function register_admin_menu() {
		// Parent Menu: ApnaStay.
		add_menu_page(
			__( 'ApnaStay Platform Admin', 'apnastay-core' ),
			__( 'ApnaStay', 'apnastay-core' ),
			'apnastay_manage_properties',
			'apnastay-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-building',
			26
		);

		// 1. Dashboard (First Submenu Item).
		add_submenu_page(
			'apnastay-dashboard',
			__( 'ApnaStay Dashboard', 'apnastay-core' ),
			__( 'Dashboard', 'apnastay-core' ),
			'apnastay_manage_properties',
			'apnastay-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// 2. Properties.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Property Listings', 'apnastay-core' ),
			__( 'Properties', 'apnastay-core' ),
			'apnastay_manage_properties',
			'apnastay-properties',
			array( $this, 'render_properties_page' )
		);

		// 3. Property Verification.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Property Verification & Inspection', 'apnastay-core' ),
			__( 'Property Verification', 'apnastay-core' ),
			'apnastay_verify_property',
			'apnastay-property-verification',
			array( $this, 'render_property_verification_page' )
		);

		// 4. Owners.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Property Owners', 'apnastay-core' ),
			__( 'Owners', 'apnastay-core' ),
			'apnastay_manage_users',
			'apnastay-owners',
			array( $this, 'render_owners_page' )
		);

		// 5. Owner Verification.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Owner KYC & Aadhaar/PAN Verification', 'apnastay-core' ),
			__( 'Owner Verification', 'apnastay-core' ),
			'apnastay_verify_owner',
			'apnastay-owner-verification',
			array( $this, 'render_owner_verification_page' )
		);

		// 6. Tenants.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Verified Tenants', 'apnastay-core' ),
			__( 'Tenants', 'apnastay-core' ),
			'apnastay_manage_users',
			'apnastay-tenants',
			array( $this, 'render_tenants_page' )
		);

		// 7. Visits.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'NFC Smart-Lock Tours & Visits', 'apnastay-core' ),
			__( 'Visits', 'apnastay-core' ),
			'apnastay_manage_properties',
			'apnastay-visits',
			array( $this, 'render_visits_page' )
		);

		// 8. Bookings.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Rental Bookings & Agreements', 'apnastay-core' ),
			__( 'Bookings', 'apnastay-core' ),
			'apnastay_manage_properties',
			'apnastay-bookings',
			array( $this, 'render_bookings_page' )
		);

		// 9. Payments.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'UPI Autopay & Zero-Brokerage Transactions', 'apnastay-core' ),
			__( 'Payments', 'apnastay-core' ),
			'apnastay_manage_payments',
			'apnastay-payments',
			array( $this, 'render_payments_page' )
		);

		// 10. Complaints.
		add_submenu_page(
			'apnastay-dashboard',
			__( '24/7 Support & Dispute Resolution', 'apnastay-core' ),
			__( 'Complaints', 'apnastay-core' ),
			'apnastay_manage_complaints',
			'apnastay-complaints',
			array( $this, 'render_complaints_page' )
		);

		// 11. Reviews.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Tenant & Landlord Reviews', 'apnastay-core' ),
			__( 'Reviews', 'apnastay-core' ),
			'apnastay_moderate_reviews',
			'apnastay-reviews',
			array( $this, 'render_reviews_page' )
		);

		// 12. Analytics.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Tier-2 Market Intelligence & Analytics', 'apnastay-core' ),
			__( 'Analytics', 'apnastay-core' ),
			'apnastay_view_analytics',
			'apnastay-analytics',
			array( $this, 'render_analytics_page' )
		);

		// 13. Settings.
		add_submenu_page(
			'apnastay-dashboard',
			__( 'Platform Settings & RBAC Policy', 'apnastay-core' ),
			__( 'Settings', 'apnastay-core' ),
			'manage_options',
			'apnastay-settings',
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
		<div class="wrap apnastay-admin-wrap">
			<div class="apnastay-admin-card">
				<div class="apnastay-badge"><?php echo esc_html( $badge ); ?></div>
				<div class="apnastay-admin-header">
					<div class="apnastay-admin-icon">
						<span class="dashicons <?php echo esc_attr( $icon_dashicon ); ?>"></span>
					</div>
					<div class="apnastay-admin-title">
						<h1><?php echo esc_html( $title ); ?></h1>
						<p><?php echo esc_html( $description ); ?></p>
					</div>
				</div>

				<div class="apnastay-admin-body">
					<h3><?php echo esc_html( $title ); ?> — Admin Architecture Registered</h3>
					<p>
						<?php esc_html_e( 'The administrative menu architecture and RBAC capability boundary are active. This interface is restricted to authorized internal staff and administrators.', 'apnastay-core' ); ?>
					</p>
					<div class="apnastay-admin-meta">
						<span class="apnastay-meta-pill">
							<strong><?php esc_html_e( 'Required Capability:', 'apnastay-core' ); ?></strong>
							<code><?php echo esc_html( $required_cap ); ?></code>
						</span>
						<span class="apnastay-meta-pill">
							<strong><?php esc_html_e( 'Headless Frontend:', 'apnastay-core' ); ?></strong>
							Next.js API V1
						</span>
						<span class="apnastay-meta-pill">
							<strong><?php esc_html_e( 'Status:', 'apnastay-core' ); ?></strong>
							<?php esc_html_e( 'Architecture Ready', 'apnastay-core' ); ?>
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
			__( 'ApnaStay Platform Dashboard', 'apnastay-core' ),
			__( 'Centralized administration for Indian Tier-2 verified long-term rentals.', 'apnastay-core' ),
			__( 'Platform Command Center', 'apnastay-core' ),
			'dashicons-chart-pie',
			'apnastay_manage_properties'
		);
	}

	/**
	 * 2. Render Properties Page.
	 */
	public function render_properties_page() {
		$this->render_admin_placeholder(
			__( 'Property Listings Management', 'apnastay-core' ),
			__( 'Review, audit, and moderate residential properties across Tier-2 Indian hubs.', 'apnastay-core' ),
			__( 'Inventory Administration', 'apnastay-core' ),
			'dashicons-building',
			'apnastay_manage_properties'
		);
	}

	/**
	 * 3. Render Property Verification Page.
	 */
	public function render_property_verification_page() {
		$this->render_admin_placeholder(
			__( 'Property Verification & Physical Inspection', 'apnastay-core' ),
			__( 'Verify title deeds, conduct physical inspections, and authorize NFC smart-lock installation.', 'apnastay-core' ),
			__( 'Verification & Inspection', 'apnastay-core' ),
			'dashicons-saved',
			'apnastay_verify_property'
		);
	}

	/**
	 * 4. Render Owners Page.
	 */
	public function render_owners_page() {
		$this->render_admin_placeholder(
			__( 'Property Owners Directory', 'apnastay-core' ),
			__( 'Manage registered landlord accounts and monitor portfolio compliance.', 'apnastay-core' ),
			__( 'User Management', 'apnastay-core' ),
			'dashicons-businessman',
			'apnastay_manage_users'
		);
	}

	/**
	 * 5. Render Owner Verification Page.
	 * Complete administrative review console for KYC & Aadhaar/PAN Verification.
	 */
	public function render_owner_verification_page() {
		if ( ! current_user_can( 'apnastay_verify_owner' ) && ! current_user_can( 'administrator' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'apnastay-core' ) );
		}

		// Handle Form Actions (Approve / Reject / Reset).
		if ( isset( $_POST['apnastay_action'] ) && 'update_owner_verification' === $_POST['apnastay_action'] ) {
			check_admin_referer( 'apnastay_update_owner_verification_nonce' );

			$target_user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
			$new_status     = isset( $_POST['new_status'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) ) ) : '';

			$allowed_statuses = array( 'unverified', 'pending', 'verified', 'rejected', 'suspended' );
			if ( $target_user_id && in_array( $new_status, $allowed_statuses, true ) ) {
				update_user_meta( $target_user_id, 'owner_verification_status', $new_status );
				update_user_meta( $target_user_id, 'apnastay_verification_status', $new_status );

				echo '<div class="notice notice-success is-dismissible"><p>' .
					sprintf(
						/* translators: 1: User ID, 2: Verification Status */
						esc_html__( 'Owner #%1$d KYC verification status updated to: %2$s', 'apnastay-core' ),
						(int) $target_user_id,
						'<strong>' . esc_html( strtoupper( $new_status ) ) . '</strong>'
					) .
					'</p></div>';
			}
		}

		// Query all users who are apnastay_owner or have an owner_verification_status.
		$owners = get_users(
			array(
				'role__in' => array( 'apnastay_owner', 'administrator' ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);
		?>
		<div class="wrap apnastay-admin-wrap">
			<div class="apnastay-admin-card">
				<div class="apnastay-badge"><?php esc_html_e( 'KYC & Identity Review Desk', 'apnastay-core' ); ?></div>
				<div class="apnastay-admin-header">
					<div class="apnastay-admin-icon">
						<span class="dashicons dashicons-id-alt"></span>
					</div>
					<div class="apnastay-admin-title">
						<h1><?php esc_html_e( 'Owner KYC & Aadhaar/PAN Verification', 'apnastay-core' ); ?></h1>
						<p><?php esc_html_e( 'Review submitted KYC documents, verify Aadhaar/PAN identity, and authorize property publishing privileges.', 'apnastay-core' ); ?></p>
					</div>
				</div>

				<div style="margin-top: 24px;">
					<?php if ( empty( $owners ) ) : ?>
						<p><?php esc_html_e( 'No property owner accounts found.', 'apnastay-core' ); ?></p>
					<?php else : ?>
						<table class="apnastay-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'User ID', 'apnastay-core' ); ?></th>
									<th><?php esc_html_e( 'Owner Name', 'apnastay-core' ); ?></th>
									<th><?php esc_html_e( 'Email', 'apnastay-core' ); ?></th>
									<th><?php esc_html_e( 'Registered Date', 'apnastay-core' ); ?></th>
									<th><?php esc_html_e( 'KYC Verification Status', 'apnastay-core' ); ?></th>
									<th><?php esc_html_e( 'Review Actions', 'apnastay-core' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $owners as $owner_user ) :
									$status = get_user_meta( $owner_user->ID, 'owner_verification_status', true );
									if ( empty( $status ) ) {
										$status = get_user_meta( $owner_user->ID, 'apnastay_verification_status', true );
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
											<span class="apnastay-status-badge apnastay-status-<?php echo esc_attr( $status_lower ); ?>">
												<?php echo esc_html( strtoupper( $status_lower ) ); ?>
											</span>
										</td>
										<td>
											<div style="display: flex; gap: 8px; flex-wrap: wrap;">
												<?php if ( 'verified' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'apnastay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="apnastay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="verified" />
														<button type="submit" class="apnastay-btn apnastay-btn-approve">
															<?php esc_html_e( 'Approve (Verify)', 'apnastay-core' ); ?>
														</button>
													</form>
												<?php endif; ?>

												<?php if ( 'rejected' !== $status_lower && 'unverified' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'apnastay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="apnastay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="rejected" />
														<button type="submit" class="apnastay-btn apnastay-btn-reject">
															<?php esc_html_e( 'Reject', 'apnastay-core' ); ?>
														</button>
													</form>
												<?php endif; ?>

												<?php if ( 'pending' !== $status_lower ) : ?>
													<form method="post" style="display: inline;">
														<?php wp_nonce_field( 'apnastay_update_owner_verification_nonce' ); ?>
														<input type="hidden" name="apnastay_action" value="update_owner_verification" />
														<input type="hidden" name="user_id" value="<?php echo (int) $owner_user->ID; ?>" />
														<input type="hidden" name="new_status" value="pending" />
														<button type="submit" class="apnastay-btn apnastay-btn-secondary">
															<?php esc_html_e( 'Set Pending', 'apnastay-core' ); ?>
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
			__( 'Verified Tenants Directory', 'apnastay-core' ),
			__( 'Manage tenant accounts, employment verification records, and rental histories.', 'apnastay-core' ),
			__( 'User Management', 'apnastay-core' ),
			'dashicons-groups',
			'apnastay_manage_users'
		);
	}

	/**
	 * 7. Render Visits Page.
	 */
	public function render_visits_page() {
		$this->render_admin_placeholder(
			__( 'NFC Smart-Lock Tour Logs', 'apnastay-core' ),
			__( 'Monitor self-guided NFC smart-lock entries and scheduled prospective tenant visits.', 'apnastay-core' ),
			__( 'Physical Access Logs', 'apnastay-core' ),
			'dashicons-location',
			'apnastay_manage_properties'
		);
	}

	/**
	 * 8. Render Bookings Page.
	 */
	public function render_bookings_page() {
		$this->render_admin_placeholder(
			__( 'Bookings & Digital Lease Agreements', 'apnastay-core' ),
			__( 'Review active rental applications and manage Aadhaar e-signed digital lease contracts.', 'apnastay-core' ),
			__( 'Lease Administration', 'apnastay-core' ),
			'dashicons-media-document',
			'apnastay_manage_properties'
		);
	}

	/**
	 * 9. Render Payments Page.
	 */
	public function render_payments_page() {
		$this->render_admin_placeholder(
			__( 'Rental Payments & UPI Autopay Ledger', 'apnastay-core' ),
			__( 'Track 100% zero-brokerage rent disbursements, security deposits, and NPCI UPI mandates.', 'apnastay-core' ),
			__( 'Financial Operations', 'apnastay-core' ),
			'dashicons-money-alt',
			'apnastay_manage_payments'
		);
	}

	/**
	 * 10. Render Complaints Page.
	 */
	public function render_complaints_page() {
		$this->render_admin_placeholder(
			__( 'Complaints & Dispute Resolution', 'apnastay-core' ),
			__( 'Manage 24/7 maintenance tickets, tenant-landlord disputes, and resolution workflows.', 'apnastay-core' ),
			__( 'Support Desk', 'apnastay-core' ),
			'dashicons-sos',
			'apnastay_manage_complaints'
		);
	}

	/**
	 * 11. Render Reviews Page.
	 */
	public function render_reviews_page() {
		$this->render_admin_placeholder(
			__( 'Review Moderation & Trust Scores', 'apnastay-core' ),
			__( 'Moderate property reviews, landlord trust ratings, and verified tenant feedback.', 'apnastay-core' ),
			__( 'Content Moderation', 'apnastay-core' ),
			'dashicons-star-filled',
			'apnastay_moderate_reviews'
		);
	}

	/**
	 * 12. Render Analytics Page.
	 */
	public function render_analytics_page() {
		$this->render_admin_placeholder(
			__( 'Tier-2 Long-Term Rental Analytics', 'apnastay-core' ),
			__( 'Market occupancy rates, yield trends across Indore/Jaipur/Surat, and platform metrics.', 'apnastay-core' ),
			__( 'Business Intelligence', 'apnastay-core' ),
			'dashicons-chart-bar',
			'apnastay_view_analytics'
		);
	}

	/**
	 * 13. Render Settings Page.
	 */
	public function render_settings_page() {
		$this->render_admin_placeholder(
			__( 'Platform Configuration & RBAC Policy', 'apnastay-core' ),
			__( 'Configure JWT/cookie authentication tokens, CORS origins, and KYC verification thresholds.', 'apnastay-core' ),
			__( 'System Configuration', 'apnastay-core' ),
			'dashicons-admin-settings',
			'manage_options'
		);
	}
}
