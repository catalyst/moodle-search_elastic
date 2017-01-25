<?php
namespace Aws\CloudFormation;

use Aws\AwsClient;

/**
 * This client is used to interact with the **AWS CloudFormation** service.
 *
 * @method \Aws\Result cancelUpdateStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise cancelUpdateStackAsync(array $args = [])
 * @method \Aws\Result continueUpdateRollback(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise continueUpdateRollbackAsync(array $args = [])
 * @method \Aws\Result createChangeSet(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createChangeSetAsync(array $args = [])
 * @method \Aws\Result createStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createStackAsync(array $args = [])
 * @method \Aws\Result deleteChangeSet(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteChangeSetAsync(array $args = [])
 * @method \Aws\Result deleteStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteStackAsync(array $args = [])
 * @method \Aws\Result describeAccountLimits(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeAccountLimitsAsync(array $args = [])
 * @method \Aws\Result describeChangeSet(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeChangeSetAsync(array $args = [])
 * @method \Aws\Result describeStackEvents(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStackEventsAsync(array $args = [])
 * @method \Aws\Result describeStackResource(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStackResourceAsync(array $args = [])
 * @method \Aws\Result describeStackResources(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStackResourcesAsync(array $args = [])
 * @method \Aws\Result describeStacks(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStacksAsync(array $args = [])
 * @method \Aws\Result estimateTemplateCost(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise estimateTemplateCostAsync(array $args = [])
 * @method \Aws\Result executeChangeSet(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise executeChangeSetAsync(array $args = [])
 * @method \Aws\Result getStackPolicy(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise getStackPolicyAsync(array $args = [])
 * @method \Aws\Result getTemplate(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise getTemplateAsync(array $args = [])
 * @method \Aws\Result getTemplateSummary(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise getTemplateSummaryAsync(array $args = [])
 * @method \Aws\Result listChangeSets(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise listChangeSetsAsync(array $args = [])
 * @method \Aws\Result listExports(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise listExportsAsync(array $args = [])
 * @method \Aws\Result listImports(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise listImportsAsync(array $args = [])
 * @method \Aws\Result listStackResources(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise listStackResourcesAsync(array $args = [])
 * @method \Aws\Result listStacks(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise listStacksAsync(array $args = [])
 * @method \Aws\Result setStackPolicy(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise setStackPolicyAsync(array $args = [])
 * @method \Aws\Result signalResource(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise signalResourceAsync(array $args = [])
 * @method \Aws\Result updateStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateStackAsync(array $args = [])
 * @method \Aws\Result validateTemplate(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise validateTemplateAsync(array $args = [])
 */
class CloudFormationClient extends AwsClient {}
