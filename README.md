# Model Inheritance for Laravel

## Installation

```php
composer require cvsouth/eloquent-inheritance
php artisan migrate
```

## Usage

### Defining classes

Extend your models from `InheritableModel` instead of the usual `Model`:

```php
class Animal extends InheritableModel
{
    public $table = "animals";
    protected $fillable =
    [
        'name',
        'species',
    ];
}
```

```php
class Bird extends Animal
{
    public $table = "birds";
    protected $fillable =
    [
        'flying',
    ];
}
```

When creating your migrations add `common_id`:

```php
class CreateAnimalsTable extends Migration
{
    public function up()
    {
        Schema::create('animals', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->bigInteger('common_id')->unsigned()->index();
            $table->string('species', 250);
            $table->string('name', 250)->nullable();
        });
    }

    public function down()
    {
        Schema::drop('animals');
    }
}
```

```php
class CreateBirdsTable extends Migration
{
    public function up()
    {
        Schema::create('birds', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->bigInteger('common_id')->unsigned()->index();
            $table->boolean('flying');
        });
    }

    public function down()
    {
        Schema::drop('birds');
    }
}
```

### Storing objects

You can then use your objects just like normal Eloquent objects:

```php
$bird = new Bird
([
   "species" => "Aratinga solstitialis", // Note: This attribute is inherited from Animal
   "flying" => true,
]);
$bird->save();

echo $bird->species;
// Aratinga solstitialis
```

### Querying objects

Again, you can query the object just like usual for Eloquent:

```php
$bird = Bird::where("species", "=", "Aratinga solstitialis")->first();

echo "This " . strtolower($bird->species) . " can " . ($bird->flying ? "" : "not ") . "fly";
// This aratinga solstitialis can fly 
```

### Primary keys at different levels of inheritance

At each level of inheritance the object has an ID. In the example above, the $bird has an Animal ID as well as a Bird ID. In addition to this each entity has a common ID called Entity ID which is consistent throughout it's class hierarchy.

Use the `id_as` method to get the id for an entity at a specific level of inheritance:

```php
// The entity's Animal ID
echo $bird->id_as(Animal::class);

// The entity's Bird ID
echo $bird->id_as(Bird::class);
```

Or use the `common_id` property to get the entities common ID:

```php
// The entity's common ID
echo $bird->common_id
```

### Relationships

Relationships work like regular eloquent relationships but bear in mind that you can reference specific levels of inheritance. For example:

```php
class Trainer extends InheritableModel
{
    public $table = "trainers";
    protected $fillable =
    [
        'name',
        'animal_id',
    ];
    
    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }
}
```

```php
class CreateTrainersTable extends Migration
{
    public function up()
    {
        Schema::create('trainers', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->bigInteger('common_id')->unsigned()->index();
            $table->string('name', 250)->nullable();
            $table->bigInteger('animal_id')->unsigned();
        });
        
        Schema::table('trainers', function ($table)
        {
            $table->foreign('animal_id')->references('id')->on('animals')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::drop('trainers');
    }
}
```

```php
$bird = Bird::where("species", "=", "Aratinga solstitialis")->first();
$trainer = new Trainer
([
    "name" => "Trainer 1",
    "animal_id" => $bird->id_as(Animal::class), // Reference the bird's Animal ID
]);
$trainer->save();

echo gettype($trainer->animal); // Bird
echo $trainer->animal->species; // Aratinga solstitialis
```
