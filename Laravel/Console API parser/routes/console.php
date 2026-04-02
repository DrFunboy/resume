<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:parse-fns')->quarterly();
