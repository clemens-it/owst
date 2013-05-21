CREATE TABLE switch (
        id integer not null primary key,
        name text not null unique,
        mode text not null default 'timer',
		  ow_type text not null,
        ow_address text not null,
        ow_pio text not null,
        failures_count integer not null default 0,
        failures_max integer not null default 10
);
CREATE TABLE time_program (
        id integer not null primary key,
        d0 integer not null default 0,
        d1 integer not null default 0,
        d2 integer not null default 0,
        d3 integer not null default 0,
        d4 integer not null default 0,
        d5 integer not null default 0,
        d6 integer not null default 0,
        valid_from text,
        valid_until text,
        override_other_programs_when_turning_off integer not null default 0,
        switch_on_time text not null,
        switch_off_time text not null,
        switch_id integer not null,
        delete_after_becoming_invalid integer not null default 0,
        switch_off_priority text not null default 'runtime',
        active integer not null default 0,
        time_switched_on integer not null default 0
, 'name' TEXT);
