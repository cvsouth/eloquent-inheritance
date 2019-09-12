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
    
    public function speak()
    {
        print($this->name . ' makes a noise');
    }
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

    public function speak()
    {
        print('AAA!');
    }

    public function fly()
    {
        $this->flying = true;
    }

    public function land()
    {
        $this->flying = false;
    }

    public function isFlying()
    {
        return $this->flying;
    }
}
```

When creating your migrations add `base_id`:

```php
class CreateAnimalsTable extends Migration
{
    public function up()
    {
        Schema::create('animals', function (Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->bigInteger('base_id')->unsigned()->index();
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
            $table->bigInteger('base_id')->unsigned()->index();
            $table->boolean('flying')->default(false);
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
]);
$bird->save();

echo $bird->species;
// Aratinga solstitialis

$bird->speak();
// AAA!
```

### Querying objects

Again, you can query the object just like usual for Eloquent:

```php
$bird = Bird::where("species", "=", "Aratinga solstitialis")->first();
$bird->fly();

echo "This " . strtolower($bird->species) . " is " . ($bird->isFlying() ? "" : "not ") . "flying";
// This aratinga solstitialis is flying

$bird->species = 'Sun Conure'
$bird->land();

echo "This " . strtolower($bird->species) . " is " . ($bird->isFlying() ? "" : "not ") . "flying";
// This sun conure is not flying
```

### Primary keys at different levels of inheritance

At each level of inheritance the object has an id. In the example above, the $bird has an animal id as well as a bird id In addition to this each model has a common id called `base_id` which is consistent throughout it's class hierarchy.

Use the `id_as` method to get the id for a model at a specific level of inheritance:

```php
// The model's animal id
echo $bird->id_as(Animal::class);

// The model's bird id
echo $bird->id_as(Bird::class);
```

Or use the `base_id` property to get the entities base id:

```php
// The model's base id
echo $bird->base_id
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
            $table->bigInteger('base_id')->unsigned()->index();
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
