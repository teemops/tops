<?php

namespace Tests\Unit;

use App\Services\RulesEngine\ConditionEvaluator;
use Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new ConditionEvaluator();
    }

    /**
     * The classic "no MFA device" style rule.
     */
    public function test_evaluates_a_count_comparison(): void
    {
        $this->assertTrue($this->evaluator->evaluate("count(\$data['MFADevices']) == 0", ['MFADevices' => []]));
        $this->assertFalse($this->evaluator->evaluate(
            "count(\$data['MFADevices']) == 0",
            ['MFADevices' => [['SerialNumber' => 'arn']]]
        ));
    }

    /**
     * isset() based rules work against the raw data.
     */
    public function test_evaluates_an_isset_check(): void
    {
        $this->assertTrue($this->evaluator->evaluate("isset(\$data['PublicIpAddress'])", ['PublicIpAddress' => '1.2.3.4']));
        $this->assertFalse($this->evaluator->evaluate("isset(\$data['PublicIpAddress'])", ['InstanceId' => 'i-123']));
    }

    /**
     * A failed API call is recorded with an __error__ marker; rules written as
     * "$data === false" are expected to match it.
     */
    public function test_treats_the_error_marker_as_false_data(): void
    {
        $errorData = ['__error__' => true, 'error_message' => 'NoSuchPublicAccessBlockConfiguration'];

        $this->assertTrue($this->evaluator->evaluate('$data === false', $errorData));
    }

    /**
     * Data without the marker is passed through as the array it is.
     */
    public function test_does_not_treat_normal_data_as_false(): void
    {
        $this->assertFalse($this->evaluator->evaluate('$data === false', ['Something' => 1]));
    }

    /**
     * An __error__ key that is not literally true is not an error marker.
     */
    public function test_ignores_a_non_true_error_marker(): void
    {
        $this->assertFalse($this->evaluator->evaluate('$data === false', ['__error__' => false]));
    }

    /**
     * A condition referencing a missing key fails closed rather than throwing.
     */
    public function test_returns_false_when_the_condition_references_missing_data(): void
    {
        $this->assertFalse($this->evaluator->evaluate("count(\$data['Missing']) > 0", []));
    }

    /**
     * A syntactically broken condition is swallowed and reported as false.
     */
    public function test_returns_false_for_a_syntactically_invalid_condition(): void
    {
        $this->assertFalse($this->evaluator->evaluate('this is not php ===', ['a' => 1]));
    }

    /**
     * Non-boolean expression results are coerced to bool.
     */
    public function test_coerces_truthy_and_falsy_results_to_bool(): void
    {
        $this->assertTrue($this->evaluator->evaluate("count(\$data['Items'])", ['Items' => [1, 2]]));
        $this->assertFalse($this->evaluator->evaluate("count(\$data['Items'])", ['Items' => []]));
    }

    /**
     * Null-coalescing conditions, as used by the shipped rulesets, evaluate cleanly.
     */
    public function test_evaluates_a_null_coalescing_condition(): void
    {
        $condition = "count(\$data['AccessKeyMetadata'] ?? []) > 0";

        $this->assertTrue($this->evaluator->evaluate($condition, ['AccessKeyMetadata' => [['AccessKeyId' => 'AKIA']]]));
        $this->assertFalse($this->evaluator->evaluate($condition, []));
    }
}
