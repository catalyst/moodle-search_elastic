<?php
namespace Aws\OpsWorks;

use Aws\AwsClient;

/**
 * This client is used to interact with the **AWS OpsWorks** service.
 *
 * @method \Aws\Result assignInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise assignInstanceAsync(array $args = [])
 * @method \Aws\Result assignVolume(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise assignVolumeAsync(array $args = [])
 * @method \Aws\Result associateElasticIp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise associateElasticIpAsync(array $args = [])
 * @method \Aws\Result attachElasticLoadBalancer(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise attachElasticLoadBalancerAsync(array $args = [])
 * @method \Aws\Result cloneStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise cloneStackAsync(array $args = [])
 * @method \Aws\Result createApp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createAppAsync(array $args = [])
 * @method \Aws\Result createDeployment(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createDeploymentAsync(array $args = [])
 * @method \Aws\Result createInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createInstanceAsync(array $args = [])
 * @method \Aws\Result createLayer(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createLayerAsync(array $args = [])
 * @method \Aws\Result createStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createStackAsync(array $args = [])
 * @method \Aws\Result createUserProfile(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise createUserProfileAsync(array $args = [])
 * @method \Aws\Result deleteApp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteAppAsync(array $args = [])
 * @method \Aws\Result deleteInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteInstanceAsync(array $args = [])
 * @method \Aws\Result deleteLayer(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteLayerAsync(array $args = [])
 * @method \Aws\Result deleteStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteStackAsync(array $args = [])
 * @method \Aws\Result deleteUserProfile(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deleteUserProfileAsync(array $args = [])
 * @method \Aws\Result deregisterEcsCluster(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deregisterEcsClusterAsync(array $args = [])
 * @method \Aws\Result deregisterElasticIp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deregisterElasticIpAsync(array $args = [])
 * @method \Aws\Result deregisterInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deregisterInstanceAsync(array $args = [])
 * @method \Aws\Result deregisterRdsDbInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deregisterRdsDbInstanceAsync(array $args = [])
 * @method \Aws\Result deregisterVolume(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise deregisterVolumeAsync(array $args = [])
 * @method \Aws\Result describeAgentVersions(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeAgentVersionsAsync(array $args = [])
 * @method \Aws\Result describeApps(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeAppsAsync(array $args = [])
 * @method \Aws\Result describeCommands(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeCommandsAsync(array $args = [])
 * @method \Aws\Result describeDeployments(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeDeploymentsAsync(array $args = [])
 * @method \Aws\Result describeEcsClusters(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeEcsClustersAsync(array $args = [])
 * @method \Aws\Result describeElasticIps(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeElasticIpsAsync(array $args = [])
 * @method \Aws\Result describeElasticLoadBalancers(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeElasticLoadBalancersAsync(array $args = [])
 * @method \Aws\Result describeInstances(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeInstancesAsync(array $args = [])
 * @method \Aws\Result describeLayers(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeLayersAsync(array $args = [])
 * @method \Aws\Result describeLoadBasedAutoScaling(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeLoadBasedAutoScalingAsync(array $args = [])
 * @method \Aws\Result describeMyUserProfile(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeMyUserProfileAsync(array $args = [])
 * @method \Aws\Result describePermissions(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describePermissionsAsync(array $args = [])
 * @method \Aws\Result describeRaidArrays(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeRaidArraysAsync(array $args = [])
 * @method \Aws\Result describeRdsDbInstances(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeRdsDbInstancesAsync(array $args = [])
 * @method \Aws\Result describeServiceErrors(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeServiceErrorsAsync(array $args = [])
 * @method \Aws\Result describeStackProvisioningParameters(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStackProvisioningParametersAsync(array $args = [])
 * @method \Aws\Result describeStackSummary(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStackSummaryAsync(array $args = [])
 * @method \Aws\Result describeStacks(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeStacksAsync(array $args = [])
 * @method \Aws\Result describeTimeBasedAutoScaling(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeTimeBasedAutoScalingAsync(array $args = [])
 * @method \Aws\Result describeUserProfiles(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeUserProfilesAsync(array $args = [])
 * @method \Aws\Result describeVolumes(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise describeVolumesAsync(array $args = [])
 * @method \Aws\Result detachElasticLoadBalancer(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise detachElasticLoadBalancerAsync(array $args = [])
 * @method \Aws\Result disassociateElasticIp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise disassociateElasticIpAsync(array $args = [])
 * @method \Aws\Result getHostnameSuggestion(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise getHostnameSuggestionAsync(array $args = [])
 * @method \Aws\Result grantAccess(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise grantAccessAsync(array $args = [])
 * @method \Aws\Result rebootInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise rebootInstanceAsync(array $args = [])
 * @method \Aws\Result registerEcsCluster(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise registerEcsClusterAsync(array $args = [])
 * @method \Aws\Result registerElasticIp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise registerElasticIpAsync(array $args = [])
 * @method \Aws\Result registerInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise registerInstanceAsync(array $args = [])
 * @method \Aws\Result registerRdsDbInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise registerRdsDbInstanceAsync(array $args = [])
 * @method \Aws\Result registerVolume(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise registerVolumeAsync(array $args = [])
 * @method \Aws\Result setLoadBasedAutoScaling(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise setLoadBasedAutoScalingAsync(array $args = [])
 * @method \Aws\Result setPermission(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise setPermissionAsync(array $args = [])
 * @method \Aws\Result setTimeBasedAutoScaling(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise setTimeBasedAutoScalingAsync(array $args = [])
 * @method \Aws\Result startInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise startInstanceAsync(array $args = [])
 * @method \Aws\Result startStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise startStackAsync(array $args = [])
 * @method \Aws\Result stopInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise stopInstanceAsync(array $args = [])
 * @method \Aws\Result stopStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise stopStackAsync(array $args = [])
 * @method \Aws\Result unassignInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise unassignInstanceAsync(array $args = [])
 * @method \Aws\Result unassignVolume(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise unassignVolumeAsync(array $args = [])
 * @method \Aws\Result updateApp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateAppAsync(array $args = [])
 * @method \Aws\Result updateElasticIp(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateElasticIpAsync(array $args = [])
 * @method \Aws\Result updateInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateInstanceAsync(array $args = [])
 * @method \Aws\Result updateLayer(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateLayerAsync(array $args = [])
 * @method \Aws\Result updateMyUserProfile(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateMyUserProfileAsync(array $args = [])
 * @method \Aws\Result updateRdsDbInstance(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateRdsDbInstanceAsync(array $args = [])
 * @method \Aws\Result updateStack(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateStackAsync(array $args = [])
 * @method \Aws\Result updateUserProfile(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateUserProfileAsync(array $args = [])
 * @method \Aws\Result updateVolume(array $args = [])
 * @method \GuzzleHttpv6\Promise\Promise updateVolumeAsync(array $args = [])
 */
class OpsWorksClient extends AwsClient {}
