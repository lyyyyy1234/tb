<?php
/*
 * WhatsApp configuration.
 *
 * Leave WHATSAPP_ENABLED = false while testing the alert logic locally.
 * To enable real WhatsApp sending later, fill in the values supplied by
 * your WhatsApp Business/Cloud API setup.
 */
define("WHATSAPP_ENABLED", false);
define("WHATSAPP_GRAPH_API_VERSION", "v23.0");
define("WHATSAPP_PHONE_NUMBER_ID", "");
define("WHATSAPP_ACCESS_TOKEN", "");
define("WHATSAPP_ADMIN_NUMBER", "");

/* Daily submission deadline, Malaysia time. */
define("METER_SUBMISSION_DEADLINE", "23:00:00");
?>
