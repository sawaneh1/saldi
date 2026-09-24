<?php
global $buttonColor;
global $buttonTxtColor;

if (!function_exists('brightenColor')) {
    /**
     * Brightens a hex color by a given amount.
     * @param string $color The hex color code (e.g., '#ff0000').
     * @param float $amount The amount to brighten (0 to 1).
     * @return string The brightened hex color code.
     */
    function brightenColor($color, $amount = 0.2) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert hex to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        // Brighten each component
        $r = min(255, $r + ($amount * (255 - $r)));
        $g = min(255, $g + ($amount * (255 - $g)));
        $b = min(255, $b + ($amount * (255 - $b)));
        
        // Convert back to hex
        return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
    }
}

if (!function_exists('darkenColor')) {
    /**
     * Darkens a hex color by a given amount.
     * @param string $color The hex color code (e.g., '#ff0000').
     * @param float $amount The amount to darken (0 to 1).
     * @return string The darkened hex color code.
     */
    function darkenColor($color, $amount = 0.2) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert hex to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        // Darken each component
        $r = max(0, $r - ($amount * $r));
        $g = max(0, $g - ($amount * $g));
        $b = max(0, $b - ($amount * $b));
        
        // Convert back to hex
        return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
    }
}

$topCol       = $buttonColor;
$butDownCol   = brightenColor($buttonColor, 0.2);
$butUpCol     = darkenColor($buttonColor, 0.2);
// 20260922 Sawaneh Page sub-bar (topbar spec 2.4): the bar is white with the page tabs as text
//                  tabs (active = Saldi-blue underline); only real actions (Luk, Ny, ...) stay
//                  as blue buttons. Icons in the shared topLine includes use currentColor.
$topStyle     = "border:0;border-bottom:1px solid #e2e6ee;color:#1c2431;border-radius:0;background-color:#ffffff;";
$buttonStyle  = "border:0;border-color:$topCol;color:$buttonTxtColor;border-radius:6px;background-color:$topCol;padding:6px 12px;font-weight:600;";
$butDownStyle = "border:0;border-bottom:2px solid $topCol;color:$topCol;border-radius:0;background-color:#ffffff;padding:6px 10px;font-weight:650;";
$butUpStyle   = "border:0;border-bottom:2px solid transparent;color:#3a4457;border-radius:0;background-color:transparent;padding:6px 10px;font-weight:500;";

?>
