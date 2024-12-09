<#1>
<?php

/* Config */
$fields = [
    "keyword" => [
        "type" => "text",
        "length" => 100,
        "fixed" => false,
        "notnull" => true
    ],
    "value" => [
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_config")) {
    $ilDB->createTable("rep_robj_xnlj_config", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_config", ["keyword"]);
}

/* Activity */
$fields = [
    "document_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ],
    "user_id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "action" => [
        "type" => "text",
        "length" => 30,
        "fixed" => false,
        "notnull" => true
    ],
    "tstamp" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "status" => [
        "type" => "text",
        "length" => 10,
        "fixed" => false,
        "notnull" => false
    ],
    "code" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "error_message" => [
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ],
    "consumed_credit" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ],
    "notified" => [
        "type" => "text",
        "length" => 1,
        "notnull" => true
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_activity")) {
    $ilDB->createTable("rep_robj_xnlj_activity", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_activity", ["document_id", "user_id", "action"]);
}

/* Tic/Tac */
$fields = [
    "exchange_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ],
    "user_id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "request_on" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "response_on" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ],
    "message" => [
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ],
    "request_url" => [
        "type" => "text",
        "length" => 400,
        "fixed" => false,
        "notnull" => true
    ],
    "response_url" => [
        "type" => "text",
        "length" => 400,
        "fixed" => false,
        "notnull" => false
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_tic")) {
    $ilDB->createTable("rep_robj_xnlj_tic", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_tic", ["exchange_id"]);
}

/* Document data */
$fields = [
    "document_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ],
    "status" => [
        // 0 => idle,
        // 1 => transcription in progress,
        // 2 => transcription ready,
        // 3 => analysis in progress,
        // 4 => analysis ready,
        // 5 => review in progress,
        // 6 => review complete,
        // 7 => h5p generation in progress,
        // 8 => h5p generation complete.
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "title" => [
        "type" => "text",
        "length" => 250,
        "fixed" => false,
        "notnull" => false
    ],
    "consumed_credit" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "doc_url" => [
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ],
    "media_type" => [ // Available: web, audio, video, document, freetext, youtube.
        "type" => "text",
        "length" => 20,
        "fixed" => false,
        "notnull" => true
    ],
    "automatic_mode" => [
        "type" => "text",
        "length" => 1,
        "fixed" => false,
        "notnull" => true
    ],
    "language" => [
        "type" => "text",
        "length" => 5,
        "fixed" => false,
        "notnull" => true
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_doc")) {
    $ilDB->createTable("rep_robj_xnlj_doc", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_doc", ["document_id"]);
}

/* Object data */
$fields = [
    "id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "is_online" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "document_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => false
    ],
];

if(!$ilDB->tableExists("rep_robj_xnlj_data")) {
    $ilDB->createTable("rep_robj_xnlj_data", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_data", ["id"]);
}

/* LP */
$fields = [
    "document_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ],
    "activity_id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "user_id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "status" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ],
    "last_change" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_lp")) {
    $ilDB->createTable("rep_robj_xnlj_lp", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_lp", ["document_id", "activity_id", "user_id"]);
}

?>

<#2>
<?php

/* h5p activity */
$fields = [
    "document_id" => [
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ],
    "type" => [
        "type" => "text",
        "length" => 250,
        "fixed" => false,
        "notnull" => false
    ],
    "generated" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ],
    "content_id" => [
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ]
];

if(!$ilDB->tableExists("rep_robj_xnlj_hfp")) {
    $ilDB->createTable("rep_robj_xnlj_hfp", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_hfp", ["content_id"]);
}

?>

<#3>
<?php

// Remove TIC/TAC.
if($ilDB->tableExists("rep_robj_xnlj_tic")) {
    $ilDB->dropTable("rep_robj_xnlj_tic");
}

?>

<#4>
<?php

// Fix H5P `authors` and `changes` column.
if ($ilDB->tableExists("rep_robj_xhfp_cont")) {
    $ilDB->manipulateF(
        "UPDATE rep_robj_xhfp_cont SET `authors` = %s WHERE `authors` = %s",
        ["text", "text"],
        ["[]", "[\"-\"]"]
    );

    $ilDB->manipulateF(
        "UPDATE rep_robj_xhfp_cont SET `changes` = %s WHERE `changes` = %s",
        ["text", "text"],
        ["[]", "[\"\"]"]
    );
}

?>

<#5>
<?php

if ($ilDB->tableColumnExists("rep_robj_xnlj_doc", "doc_url")) {
    $ilDB->modifyTableColumn(
        "rep_robj_xnlj_doc",
        "doc_url",
        [
            "type" => "blob",
            "length" => 65000,
            "notnull" => true,
            "default" => null,
        ]
    );
}

?>
