<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prune Empty Regions With The Tagging API
    |--------------------------------------------------------------------------
    |
    | A scan fans out one queue job per (service, region). Most accounts run
    | workloads in two or three regions, so most of those jobs find nothing. With
    | this enabled, one tag:GetResources call per region decides which services
    | are worth dispatching there — a large saving as the number of supported
    | services grows.
    |
    | It defaults to OFF, because GetResources reports only "tagged or previously
    | tagged" resources. In an account that does not tag consistently, a service
    | can hold resources and still be absent from the index; skipping it would
    | mark that region clean without ever looking at it. The pruning code refuses
    | to act on an empty or failed index for this reason, but it cannot detect
    | partially-tagged accounts. Turn this on when you know the estate is tagged.
    |
    */
    'prune_regions_with_tagging' => env('SCAN_PRUNE_REGIONS_WITH_TAGGING', false),

];
