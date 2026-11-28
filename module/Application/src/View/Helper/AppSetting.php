<?php
namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AppSetting extends AbstractHelper
{
    private array $settings;

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * No args  => full settings array (what login.phtml uses)
     * With key => single value, or $default if missing
     */
    public function __invoke(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->settings;
        }
        return $this->settings[$key] ?? $default;
    }
}