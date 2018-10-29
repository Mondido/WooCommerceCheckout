<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var string $payment_url */
?>
<style type="text/css">
    .order_details {
        display: none;
    }

    #mondido-iframe {
        height: 1000px;
        width: 100%;
    }
</style>
<iframe id="mondido-iframe"
        src="<?php echo esc_html($payment_url); ?>"
        frameborder="0" scrolling="no">
</iframe>
<script>
    iFrameResize( [], '#mondido-iframe' );
</script>
