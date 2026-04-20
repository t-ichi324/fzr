<?php

namespace Fzr;

/**
 * システム定数
 */
class Config
{
    const DEFAULT_INI = "env.ini";
    const DEFAULT_ROUTE = "index";
    const DEFAULT_VIEW = "index";
    const DIR_APP   = "app";
    const DIR_CTRL  = "app/controllers";
    const DIR_VIEW  = "app/views";
    const DIR_MODELS  = "app/models";
    const DIR_STORAGE = "storage";
    const DIR_DB    = "storage/db";
    const DIR_LOG   = "storage/log";
    const DIR_TEMP  = "storage/temp";
    const CTRL_PFX = "";
    const CTRL_SFX = "Controller";
    const CTRL_EXT = ".php";
    const ERR_VIEW_PFX = "";
    const ERR_VIEW_SFX = "";
    const CLI_FILE = "core-cli.php";
}
