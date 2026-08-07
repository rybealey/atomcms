<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCameraWebTable extends Migration
{
    /**
     * Atom's other camera_web migration (add_visible_to_camera_web_table) only
     * alters this table and is guarded to skip when it doesn't exist - it
     * assumes the table itself ships in the emulator's base SQL dump, which is
     * true for Arcturus but not for PlusEMU (confirmed via `SHOW TABLES` on the
     * live Plus schema). Without this, CameraWeb::query() (used by the
     * homepage's "recent photos" widget and the camera/photo pages) 500s with
     * "Base table or view not found" on every request under the Plus driver.
     */
    public function up()
    {
        if (Schema::hasTable('camera_web')) {
            return;
        }

        Schema::create('camera_web', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('room_id')->default(0);
            $table->unsignedInteger('timestamp');
            $table->string('url', 128)->default('');
            $table->boolean('visible')->default(true);
        });
    }

    public function down()
    {
        Schema::dropIfExists('camera_web');
    }
}
