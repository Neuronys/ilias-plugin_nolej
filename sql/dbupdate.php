<#1>
<?php

/* Config */
$fields = array(
    "keyword" => array(
        "type" => "text",
        "length" => 100,
        "fixed" => false,
        "notnull" => true
    ),
    "value" => array(
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_config")) {
    $ilDB->createTable("rep_robj_xnlj_config", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_config", array("keyword"));
}

/* Activity */
$fields = array(
    "document_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ),
    "user_id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "action" => array(
        "type" => "text",
        "length" => 30,
        "fixed" => false,
        "notnull" => true
    ),
    "tstamp" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "status" => array(
        "type" => "text",
        "length" => 10,
        "fixed" => false,
        "notnull" => false
    ),
    "code" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "error_message" => array(
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ),
    "consumed_credit" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ),
    "notified" => array(
        "type" => "text",
        "length" => 1,
        "notnull" => true
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_activity")) {
    $ilDB->createTable("rep_robj_xnlj_activity", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_activity", array("document_id", "user_id", "action"));
}

/* TicTac */
$fields = array(
    "exchange_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ),
    "user_id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "request_on" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "response_on" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ),
    "message" => array(
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ),
    "request_url" => array(
        "type" => "text",
        "length" => 400,
        "fixed" => false,
        "notnull" => true
    ),
    "response_url" => array(
        "type" => "text",
        "length" => 400,
        "fixed" => false,
        "notnull" => false
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_tic")) {
    $ilDB->createTable("rep_robj_xnlj_tic", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_tic", array("exchange_id"));
}

/* Document data */
$fields = array(
    "document_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ),
    "status" => array(
        /**
         * 0 => idle,
         * 1 => transcripting,
         * 2 => transcription ready,
         * 3 => analyzing,
         * 4 => analysis ready,
         * 5 => review,
         * 6 => review ready,
        */
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "title" => array(
        "type" => "text",
        "length" => 250,
        "fixed" => false,
        "notnull" => false
    ),
    "consumed_credit" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "doc_url" => array(
        "type" => "text",
        "length" => 200,
        "fixed" => false,
        "notnull" => true
    ),
    "media_type" => array(
        /**
         * Available: web, audio, video, document, freetext, youtube.
         * Soon: slide.
         */
        "type" => "text",
        "length" => 20,
        "fixed" => false,
        "notnull" => true
    ),
    "automatic_mode" => array(
        "type" => "text",
        "length" => 1,
        "fixed" => false,
        "notnull" => true
    ),
    "language" => array(
        "type" => "text",
        "length" => 5,
        "fixed" => false,
        "notnull" => true
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_doc")) {
    $ilDB->createTable("rep_robj_xnlj_doc", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_doc", array("document_id"));
}

/* Object data */
$fields = array(
    "id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "is_online" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "document_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => false
    ),
);

if(!$ilDB->tableExists("rep_robj_xnlj_data")) {
    $ilDB->createTable("rep_robj_xnlj_data", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_data", array("id"));
}

/* LP */
$fields = array(
    "document_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ),
    "activity_id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "user_id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "status" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    ),
    "last_change" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_lp")) {
    $ilDB->createTable("rep_robj_xnlj_lp", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_lp", array("document_id", "activity_id", "user_id"));
}

?>

<#2>
<?php

/* h5p activity */
$fields = array(
    "document_id" => array(
        "type" => "text",
        "length" => 50,
        "fixed" => false,
        "notnull" => true
    ),
    "type" => array(
        "type" => "text",
        "length" => 250,
        "fixed" => false,
        "notnull" => false
    ),
    "generated" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => false
    ),
    "content_id" => array(
        "type" => "integer",
        "length" => 4,
        "notnull" => true
    )
);

if(!$ilDB->tableExists("rep_robj_xnlj_hfp")) {
    $ilDB->createTable("rep_robj_xnlj_hfp", $fields);
    $ilDB->addPrimaryKey("rep_robj_xnlj_hfp", array("content_id"));
}

?>
