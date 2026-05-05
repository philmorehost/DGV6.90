<?php
	//User Registration
	createVendorEmailTemplateIfNotExists(
		"user-reg",
		"Welcome to Our Platform - Complete Your Registration",
		"Dear {firstname} {lastname},\n\nThank you for registering with us. We are excited to have you on board!\n\nPlease find below the details you provided during registration:\n\n- Email address: {email}\n- Phone number: {phone}\n- Username: {username}\n- Home address: {address}\n\nIf you have any questions or need assistance, feel free to contact us.\n\nBest regards,\nThe Support Team"
	);

	//User Login
	createVendorEmailTemplateIfNotExists(
		"user-log",
		"User Login Notification",
		"Hello {firstname} {lastname},\n\nYour login details are as follows:\nUsername: {username}\nIP Address: {ip_address}\n\nThank you,\nThe Support Team"
	);

	//User Password Update
	createVendorEmailTemplateIfNotExists(
		"user-pass-update",
		"Password Update Notification",
		"Dear {firstname} {lastname},\n\nYour password has been successfully updated.\n\nIf you did not make this change, please contact our support team immediately.\n\nBest regards,\nThe Support Team"
	);

	//User Auto Password Generate (Forgot Password)
	createVendorEmailTemplateIfNotExists(
		"user-auto-pass-generate",
		"Forgot Password Notification",
		"Dear {firstname} {lastname},\n\nYour password has been successfully updated.\n\nYour new password is: {password}\n\nIf you did not make this change, please contact our support team immediately.\n\nBest regards,\nThe Support Team"
	);

	//User Account Update
	createVendorEmailTemplateIfNotExists(
		"user-account-update",
		"Account Information Updated",
		"Dear {firstname} {lastname},\n\nYour account information has been successfully updated.\n\nDetails:\nEmail: {email}\nPhone: {phone}\nAddress: {address}\nSecurity Answer: {security_answer}\n\nThank you for keeping your information up to date.\n\nSincerely,\nThe Support Team"
	);

	//User Account Recovery
	createVendorEmailTemplateIfNotExists(
		"user-account-recovery",
		"Password Recovery",
		"Hello {firstname} {lastname},\n\nWe received a request to recover your account password.\n\nYour recovery code is: {recovery_code}\n\nPlease use this code to reset your password.\n\nBest regards,\nThe Support Team"
	);

	//User Account Status
	createVendorEmailTemplateIfNotExists(
		"user-account-status",
		"User Account Status Update",
		"Hello {firstname} {lastname},\n\nWe are writing to inform you about your account status.\n\nYour account is currently {account_status}.\n\nThank you.\n\nSincerely,\nThe Management"
	);

	//User API Status
	createVendorEmailTemplateIfNotExists(
		"user-api-status",
		"User API Status Update",
		"Hello {firstname}, {lastname}\n\nWe wanted to inform you about the current status of your API:\n\n{api_status}\n\nBest regards,\nThe API Team"
	);

	//User 
	createVendorEmailTemplateIfNotExists(
		"user-upgrade",
		"Upgrade Notification",
		"Hello {firstname} {lastname},\n\nWe are pleased to inform you that your account has been upgraded to {account_level}. Thank you for choosing our service.\n\nBest regards,\nThe Team"
	);

	//User Referral Commission
	createVendorEmailTemplateIfNotExists(
		"user-referral-commission",
		"Referral Commission Earned",
		"Hello {firstname} {lastname},\n\nWe are pleased to inform you that you have earned a referral commission of {referral_commission}.\n\nThe commission was earned from your referral, {referree}, who is currently at {account_level} account level.\n\nThank you for your participation in our referral program!\n\nBest regards,\nThe Support Team"
	);

	//User Transaction (Admin)
	createVendorEmailTemplateIfNotExists(
		"user-transactions",
		"Transaction Details",
		"Hello {admin_firstname}, {admin_lastname}\n\nA transaction has been made by user {username}, {firstname}.\n\nPrevious balance: {balance_before}\nNew balance: {balance_after}\n\nAmount: {amount}\nDescription: {description}\nType: {type}"
	);

	//User Credit/Debit Transaction
	createVendorEmailTemplateIfNotExists(
		"user-funding",
		"Account Update: Transaction Details",
    	"Hello {firstname} {lastname},\n\nYour account has been updated with the following transaction details:\n\n- Balance Before: {balance_before}\n- Balance After: {balance_after}\n- Amount: {amount}\n- Description: {description}\n- Type: {type}\n\nIf you have any questions or concerns, feel free to reach out to us.\n\nBest regards,\nThe Support Team"
	);

	//User Refund
	createVendorEmailTemplateIfNotExists(
		"user-refund",
		"Refund Notification",
		"Dear {firstname} {lastname},\n\nWe are pleased to inform you that a refund has been processed for you.\n\nAmount: {amount}\nDescription: {description}\n\nIf you have any questions or concerns, feel free to reach out to our customer support team.\n\nBest regards,\nThe Support Team"
	);

	//User KYC Submitted
	createVendorEmailTemplateIfNotExists(
		"user-kyc-submitted",
		"KYC Verification Submitted",
		"Dear {firstname},\n\nYour KYC verification documents have been successfully submitted and are now under review. This process usually takes 24-48 hours.\n\nWe will notify you once the review is complete.\n\nBest regards,\nThe Support Team"
	);

	//User KYC Admin Alert
	createVendorEmailTemplateIfNotExists(
		"user-kyc-admin-alert",
		"New KYC Submission for Review",
		"Hello Admin,\n\nA new KYC verification has been submitted by user {username} ({firstname} {lastname}).\n\nPlease log in to the admin panel to review the documents.\n\nBest regards,\nSystem Monitor"
	);

	//User KYC Approved
	createVendorEmailTemplateIfNotExists(
		"user-kyc-approved",
		"KYC Verification Approved",
		"Dear {firstname},\n\nCongratulations! Your KYC verification has been approved. You now have full access to all platform features.\n\nBest regards,\nThe Team"
	);

	//User KYC Try Again
	createVendorEmailTemplateIfNotExists(
		"user-kyc-try-again",
		"KYC Verification: Action Required",
		"Dear {firstname},\n\nYour recent KYC verification attempt was not successful. The administrator has requested that you try again.\n\nReason: {reason}\n\nPlease log in and resubmit clear documents.\n\nBest regards,\nThe Support Team"
	);

	//User KYC Rejected
	createVendorEmailTemplateIfNotExists(
		"user-kyc-rejected",
		"KYC Verification Rejected",
		"Dear {firstname},\n\nWe regret to inform you that your KYC verification has been rejected.\n\nReason: {reason}\n\nPlease contact support for further assistance.\n\nBest regards,\nThe Management"
	);

	//Vendor Registration
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-reg",
		"Vendor Registration Confirmation",
		"Hello,\n\nWe are delighted to welcome you as a potential vendor.\n\nPlease find below the details you provided:\n\nFirst Name: {firstname}\nLast Name: {lastname}\nEmail: {email}\nPhone: {phone}\nAddress: {address}\nWebsite: {website}\n\nThank you for your interest in partnering with us.\n\nBest regards,\nThe Vendor Registration Team"
	);

	//Vendor Login
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-log",
		"Vendor Login Notification",
		"Hello {firstname}, {lastname},\n\nWe wanted to inform you that there has been a login to your vendor account. Here are the details:\n\nName: {firstname}, {lastname}\nEmail: {email}\nIP Address: {ip_address}\n\nIf this login was not authorized by you, please contact support immediately.\n\nBest regards,\nThe Vendor Management Team"
	);

	//Vendor Password Update
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-pass-update",
		"Password Update Required",
		"Hello {firstname} {lastname},\n\nWe kindly remind you to update your password as soon as possible. This is essential for the security of your account.\n\nThank you for your attention to this matter.\n\nBest regards,\nThe Vendor Team"
	);

	//Vendor Account Update
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-account-update",
		"Vendor Account Update",
		"Dear {firstname} {lastname},\n\nYour account information has been updated successfully.\n\nHere are your updated details:\nEmail: {email}\nPhone: {phone}\nAddress: {address}\nWebsite: {website}\n\nIf you have any questions or concerns, please feel free to reach out to us.\n\nBest regards,\nThe Vendor Management Team"
	);

	//Vendor Password Recovery
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-account-recovery",
		"Password Recovery",
		"Hello {firstname} {lastname},\n\nYou have requested a password recovery for your vendor account.\n\nPlease use the following recovery code to reset your password: {recovery_code}\n\nIf you did not request this, please disregard this email.\n\nBest regards,\nThe Vendor Team"
	);

	//Vendor Account Status
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-account-status",
		"Vendor Account Status",
		"Hello {firstname}, {lastname}\n\nYour account status is: {account_status}"
	);

	//Vendor Transaction (Super Admin)
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-transactions",
		"Vendor Transaction Details",
		"Hello {admin_firstname} {admin_lastname},\n\nA new transaction has been made by a vendor. Here are the details:\n\nVendor Email: {email}\nVendor Name: {firstname}\nBalance Before Transaction: {balance_before}\nBalance After Transaction: {balance_after}\nTransaction Amount: {amount}\nDescription: {description}\nTransaction Type: {type}\n\nBest regards,\nThe Admin Team"
	);

	//Vendor Credit/Debit
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-funding",
		"Vendor Credit/Debit Notification",
		"Hello {firstname} {lastname},\n\nThis email is to inform you about a recent transaction on your account.\n\nBalance Before: {balance_before}\nBalance After: {balance_after}\nAmount: {amount}\nDescription: {description}\nType: {type}\n\nThank you.\n"
	);

	//Vendor Refund Notification
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-refund",
		"Vendor Refund Notification",
		"Hello {firstname} {lastname},\n\nWe are writing to inform you that a refund has been issued to you in the amount of {amount} for the following reason:\n\n{description}\n\nThank you.\n\nSincerely,\nThe Management Team"
	);

	// New Vendor Pending - Admin Alert
	createSuperAdminEmailTemplateIfNotExists(
		"new-vendor-pending-admin-alert",
		"New Vendor Registration Pending Approval",
		"Hello Admin,\n\nA new vendor has registered and is awaiting your approval.\n\n- Name: {firstname} {lastname}\n- Email: {email}\n- Website: {website}\n\nPlease log in to the admin panel to review and approve their registration.\n\nBest regards,\nYour System"
	);

	// Vendor Welcome & Activation
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-welcome-activated",
		"Welcome! Your Vendor Account is Now Active",
		"Dear {firstname} {lastname},\n\nCongratulations! Your vendor account has been approved and is now active.\n\nYou can now log in to your dashboard and start using our services.\n\nYour subscription is active until {expiry_date}.\n\nTo set up your domain, please use the following details:\n\nNameservers:\n{domain_nameservers}\n\nIf you intend to use a subdomain, please use the following IP for A records:\n{domain_ip_address}\n\nDomain name registration is not free. You can register your domain through our suggested registrar: {domain_registrar_url}\n\nBest regards,\nThe Team"
	);

	// Vendor Rejection
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-rejection",
		"Update on Your Vendor Application",
		"Dear {firstname} {lastname},\n\nThank you for your interest in becoming a vendor. After careful review, we regret to inform you that we are unable to approve your application at this time.\n\nIf you have any questions, please contact our support team.\n\nSincerely,\nThe Vendor Management Team"
	);

	// Vendor Subscription Reminder
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-subscription-reminder",
		"Your Subscription is Expiring Soon",
		"Dear {firstname} {lastname},\n\nThis is a reminder that your vendor subscription is due to expire on {expiry_date}.\n\nPlease renew your subscription to ensure uninterrupted access to our services.\n\nBest regards,\nThe Team"
	);

	// Vendor Subscription Expired
	createSuperAdminEmailTemplateIfNotExists(
		"vendor-subscription-expired",
		"Your Subscription Has Expired",
		"Dear {firstname} {lastname},\n\nYour vendor subscription has expired, and your account has been temporarily deactivated.\n\nPlease renew your subscription to reactivate your account and continue using our services.\n\nBest regards,\nThe Team"
	);