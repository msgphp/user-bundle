# Changelog

## [v0.15.0](https://github.com/msgphp/user-bundle/tree/v0.15.0) (2020-02-25)

- Update Symfony dependencies to allow 5.0 packages [\#379](https://github.com/msgphp/msgphp/pull/379)


## [v0.14.0](https://github.com/msgphp/user-bundle/tree/v0.14.0) (2019-12-15)

- deny overwriting msgphp\_user.role\_providers [\#368](https://github.com/msgphp/msgphp/pull/368)
- Generate user menu [\#367](https://github.com/msgphp/msgphp/pull/367)
- Generate translations [\#366](https://github.com/msgphp/msgphp/pull/366)
- Flatten generated structure [\#361](https://github.com/msgphp/msgphp/pull/361)


## [v0.13.0](https://github.com/msgphp/user-bundle/tree/v0.13.0) (2019-08-03)

- Add make:user:msgphp --credential option [\#355](https://github.com/msgphp/msgphp/pull/355)
- Allow no-interaction option to generate the files [\#353](https://github.com/msgphp/msgphp/pull/353)
- Remove User/Password namespace [\#350](https://github.com/msgphp/msgphp/pull/350)
- Update to symfony messenger 4.3 \(fix deprecations\) [\#349](https://github.com/msgphp/msgphp/pull/349)


## [v0.12.0](https://github.com/msgphp/user-bundle/tree/v0.12.0) (2019-07-21)

- Prevent username guessing [\#343](https://github.com/msgphp/msgphp/pull/343)
- Removed "msgphp.messenger.console\_message\_receiver" service [\#340](https://github.com/msgphp/msgphp/pull/340)


## [v0.11.0](https://github.com/msgphp/user-bundle/tree/v0.11.0) (2019-07-13)

- mark DomainId::\_\_toString internal, rename Username::\_\_toString to toString [\#324](https://github.com/msgphp/msgphp/pull/324)
- generate change username/password forms [\#322](https://github.com/msgphp/msgphp/pull/322)
- Generate default param converters [\#321](https://github.com/msgphp/msgphp/pull/321)
- Rename SecurtiyUserProvider to UserIdentityProvider [\#320](https://github.com/msgphp/msgphp/pull/320)
- Rename SecurtiyUser to UserIdentity [\#319](https://github.com/msgphp/msgphp/pull/319)
- add ResetUserPassword command [\#318](https://github.com/msgphp/msgphp/pull/318)
- Rename EventSourcingCommandHandlerTrait::handle\(\) to handleEvent\(\) [\#317](https://github.com/msgphp/msgphp/pull/317)
- Add hard return-type on User::getCredential\(\) [\#316](https://github.com/msgphp/msgphp/pull/316)
- Add CancelUserPasswordRequest command [\#315](https://github.com/msgphp/msgphp/pull/315)
- Add form UserExtension [\#313](https://github.com/msgphp/msgphp/pull/313)


## [v0.10.0](https://github.com/msgphp/user-bundle/tree/v0.10.0) (2019-03-27)

- Drop support for MsgPhp\ entity class reference in username\_lookup [\#311](https://github.com/msgphp/msgphp/pull/311)
- Removed type suffixes [\#310](https://github.com/msgphp/msgphp/pull/310)
- Renamed Infra\ to Infrastructure\ [\#309](https://github.com/msgphp/msgphp/pull/309)
- Moved Password\PasswordProtectedInterface & CredentialInterface to Credential\ [\#308](https://github.com/msgphp/msgphp/pull/308)
- Removed Entity\ namespace [\#307](https://github.com/msgphp/msgphp/pull/307)
- Removed option "password\_confirm\_current" + default "inherit\_data" in HashedPasswordType [\#306](https://github.com/msgphp/msgphp/pull/306)
- add SecurityUserHashingFactory [\#305](https://github.com/msgphp/msgphp/pull/305)
- Renamed PasswordHashing to GenericPasswordHashing [\#304](https://github.com/msgphp/msgphp/pull/304)
- add SecurityUser::getPasswordAlgorithm\(\) [\#303](https://github.com/msgphp/msgphp/pull/303)
- Rename Entity\{Fields,Features}\ to Model\ [\#301](https://github.com/msgphp/msgphp/pull/301)
- Revise user credentials [\#298](https://github.com/msgphp/msgphp/pull/298)
- Drop first-class salted password support [\#297](https://github.com/msgphp/msgphp/pull/297)
- Rename Infra\Uuid\\<Entity\>Id to \<Entity\>Uuid [\#286](https://github.com/msgphp/msgphp/pull/286)
- Rename \<Entity\>Id to Scalar\<Entity\>Id [\#285](https://github.com/msgphp/msgphp/pull/285)


## [v0.9.1](https://github.com/msgphp/user-bundle/tree/v0.9.1) (2019-02-20)

- disallow empty string ID values [\#281](https://github.com/msgphp/msgphp/pull/281)
- fix username syncing \(removes UsernameRepositoryInterface::findAllFromTargets\(\)\) [\#280](https://github.com/msgphp/msgphp/pull/280)


## [v0.9.0](https://github.com/msgphp/user-bundle/tree/v0.9.0) (2019-02-09)

- Pass context along event messages [\#278](https://github.com/msgphp/msgphp/pull/278)
- Remove DomainIdentityMappingInterface +  DomainIdentityHelper [\#276](https://github.com/msgphp/msgphp/pull/276)
- Remove entity to identifier mapping [\#271](https://github.com/msgphp/msgphp/pull/271)
- \[BC-BREAK\] Require expected ID type in domain commands [\#269](https://github.com/msgphp/msgphp/pull/269)
- Drop SimpleBus support [\#260](https://github.com/msgphp/msgphp/pull/260)
- Drop GLOB\_BRACE dependency [\#259](https://github.com/msgphp/msgphp/pull/259)


## [v0.8.0](https://github.com/msgphp/user-bundle/tree/v0.8.0) (2018-12-01)

- auto create role during user:role:add [\#231](https://github.com/msgphp/msgphp/pull/231)
- remove plain password from memory [\#227](https://github.com/msgphp/msgphp/pull/227)
- enable user-eav in bundle [\#226](https://github.com/msgphp/msgphp/pull/226)
- setup user-eav bridge [\#225](https://github.com/msgphp/msgphp/pull/225)
