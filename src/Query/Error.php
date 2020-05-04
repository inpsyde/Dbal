<?php

declare(strict_types=1);

namespace Inpsyde\Dbal\Query;

final class Error extends \Error
{
    /**
     * @param \Throwable $error
     * @return \Inpsyde\Dbal\Query\Error
     */
    public function merge(\Throwable $error): Error
    {
        return new Error($error->getMessage(), (int)$error->getCode(), $this);
    }
    
    /**
     * @return string
     */
    public function serialize(): string
    {
        $messages = [$this->getMessage()];
        $previous = $this->getPrevious();
        while ($previous) {
            $messages[] = $previous->getMessage();
            $previous = $previous->getPrevious();
        }

        return implode("\n> ", array_reverse($messages));
    }
}
