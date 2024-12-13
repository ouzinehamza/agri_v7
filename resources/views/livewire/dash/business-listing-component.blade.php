<?php
    use function Laravel\Folio\{middleware, name};
	middleware('auth');
    name('dashboard');
?>
<div>
    {{ $this->table }}
</div>
