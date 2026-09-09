<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Exceptions;

use RuntimeException;

/**
 * A tool-level problem the LLM can fix by changing its input (unknown id,
 * ambiguous name, missing argument). KingtimeTool turns it into a tool
 * error response with the message as-is.
 */
class McpToolException extends RuntimeException {}
