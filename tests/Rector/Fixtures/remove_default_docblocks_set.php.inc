<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Seeder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    }
};

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    }
}
-----
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Seeder;

return new class extends Migration
{
    public function up(): void
    {
    }
};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
    }
}
