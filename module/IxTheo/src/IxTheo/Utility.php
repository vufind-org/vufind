<?
namespace IxTheo;

class Utility {
    public static function getUserTypeFromUsedEnvironment() {
        $vufind_local_dir = getenv('VUFIND_LOCAL_DIR');
        $instance_type = basename($vufind_local_dir);
        return $instance_type;
    }
}
