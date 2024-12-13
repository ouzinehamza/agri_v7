<?php
    use function Laravel\Folio\{middleware, name};
	middleware('auth');
    name('dashboard');
?>

<div>
    
    <div>
        {{ $this->table }}
    </div>  
</div>
