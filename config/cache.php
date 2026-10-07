<?php
return ['default'=>env('CACHE_STORE','redis'),'stores'=>['array'=>['driver'=>'array','serialize'=>false],'file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')],'redis'=>['driver'=>'redis','connection'=>'cache','lock_connection'=>'default']],'prefix'=>env('CACHE_PREFIX','arions_finance_cache')];
