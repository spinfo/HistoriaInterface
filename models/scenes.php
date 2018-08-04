<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/scene.php');

class Scenes {

    protected static $instance = null;

    public $table;
    public $join_mapstops_table;

    static function instance() {
        if (static::$instance == null) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    protected function __construct() {
        $this->table = DB::table_name('scenes');
        $this->join_mapstops_table = DB::table_name('mapstops_to_scenes');
    }

    public function get_all() {
        $posts = \get_posts(array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => - 1,
        ));

        $scenes = array();
        foreach ($posts as $post) {
            $scene = $this->get($post->ID);
            $scenes[] = $scene;
            unset($scene);
        }

        return $scenes;
    }

    public function get($id) {
        $post = \get_post($id);

        if(empty($post)) {
            return null;
        }

        $scene = new Scene();
        $scene->id = $post->ID;
        $scene->post_id = $post->ID;
        $scene->title = $post->post_title;
        $scene->name = $post->post_name;
        $scene->description = $post->post_content;
        $scene->excerpt = $post->post_excerpt;
        $scene->created_at = new \DateTime($post->post_date);
        $scene->updated_at = new \DateTime($post->post_modified);
        $scene->src = wp_get_attachment_image_src($post->ID, [960, 720])[0];

        $select = "SELECT tour_id FROM $this->table";
        $result = DB::get($select, array('post_id' => $scene->id));
        if ($result) {
            $scene->tour_id = (int)$result->tour_id;
        }

        $sql = "SELECT ms.mapstop_id, ms.coordinate_id FROM " . $this->join_mapstops_table . " as ms";
        $sql .= " INNER JOIN " . Mapstops::instance()->table . " as m";
        $sql .= " ON m.id = ms.mapstop_id";
        $sql .= " WHERE ms.scene_id = %d";
        $sql .= " ORDER BY m.position ASC";
        $result = DB::list_by_query($sql, [$scene->id]);
        if ($result) {
            foreach ($result as $row) {
                $mapstop = Mapstops::instance()->get((int)$row->mapstop_id);
                if ($mapstop) {
                    $scene->mapstops[] = $mapstop;
                    $scene->mapstop_ids[] = $mapstop->id;
                }
                if (!is_null($row->coordinate_id)) {
                    $coordinate = Coordinates::instance()->get((int)$row->coordinate_id);
                    if ($coordinate) {
                        $scene->coordinates[$mapstop->id] = $coordinate;
                        $scene->coordinate_ids[$mapstop->id] = $coordinate->id;
                    }
                }
            }
        }

        return $scene;
    }

    public function save(Scene $scene) {
        try {
            if (!$scene->is_valid()) {
                debug_log("Not inserting invalid scene. Messages:");
                $scene->debug_log_messages();
                return null;
            }

            $position = $this->db_next_position($scene->tour_id);

            DB::start_transaction();
            $result = DB::insert($this->table, array(
                'tour_id' => $scene->tour_id,
                'post_id' => $scene->id,
                'position' => $position,
            ));
            if(!$result || $result == DB::BAD_ID) {
                DB::rollback_transaction();
                throw new DB_Exception("Could not insert valid place.");
            }

            DB::commit_transaction();
            return $scene;
        }
        catch (DB_Exception $e) {
            debug_log("Error saving model: " . $e->getMessage());
            return null;
        }
    }

    public function get_possible_scenes($tour) {
        $tour = Tours::instance()->get($tour->id, false, false, true);
        $scenes = $this->get_all();

        $sql = "SELECT post_id as id FROM " . Scenes::instance()->table;
        $result = DB::list_by_query($sql);
        $taken = array();
        foreach ($result as $obj) {
            $taken[] = $obj->id;
        }

        $scenes = array_filter($scenes, function ($p) use ($scenes, $taken, $tour) {
            return $p->description === "tour#" . $tour->id && !in_array($p->id, $taken);
        });
        return $scenes;
    }

    public function delete($scene) {
        return DB::delete($this->table, array('post_id' => $scene->id));
    }

    private function db_next_position($tour_id) {
        $select = "SELECT MAX(position) AS maxpos FROM $this->table";
        $result = DB::get($select, array('tour_id' => $tour_id));

        if(is_null($result) || !property_exists($result, 'maxpos')) {
            debug_log("Bad position lookup for tour_id: '$tour_id'");
            return false;
        } else {
            // there is no mapstop for the tour yet, so return 1.
            if(is_null($result->maxpos)) {
                return 1;
            }
            // there already is a position for the tour, return increment.
            else {
                return $result->maxpos + 1;
            }
        }
    }
}

?>