<?php

class FormattingOptions
{
	public int $responseMinRecord = 0;
	public int $responseMaxRecord = 0;

	/** @var array<int, string|int> */
	public array $selectedColumns = [];

	public string $responseCompletionState = '';
	public string $headingFormat = '';
	public string $answerFormat = '';
	public string $csvFieldSeparator = ',';
	public string $output = '';
}
