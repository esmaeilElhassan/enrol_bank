<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/bank/externallib.php');

class enrol_bank_external_testcase extends externallib_advanced_testcase {

    /**
     * Test get_instance_info
     */
    public function test_get_instance_info() {
        global $DB;

        $this->resetAfterTest(true);

        // Check if bank enrolment plugin is enabled.
        $bankplugin = enrol_get_plugin('bank');
        $this->assertNotEmpty($bankplugin);

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);

        $coursedata = new stdClass();
        $coursedata->visible = 0;
        $course = bank::getDataGenerator()->create_course($coursedata);

        // Add enrolment methods for course.
        $instanceid1 = $bankplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'name' => 'Test instance 1',
                                                                'customint6' => 1,
                                                                'roleid' => $studentrole->id));
        $instanceid2 = $bankplugin->add_instance($course, array('status' => ENROL_INSTANCE_DISABLED,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 2',
                                                                'roleid' => $studentrole->id));

        $instanceid3 = $bankplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'roleid' => $studentrole->id,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 3',
                                                                'password' => 'test'));

        $enrolmentmethods = $DB->get_records('enrol', array('courseid' => $course->id, 'status' => ENROL_INSTANCE_ENABLED));
        $this->assertCount(3, $enrolmentmethods);

        $this->setAdminUser();
        $instanceinfo1 = enrol_bank_external::get_instance_info($instanceid1);
        $instanceinfo1 = external_api::clean_returnvalue(enrol_bank_external::get_instance_info_returns(), $instanceinfo1);

        $this->assertEquals($instanceid1, $instanceinfo1['id']);
        $this->assertEquals($course->id, $instanceinfo1['courseid']);
        $this->assertEquals('bank', $instanceinfo1['type']);
        $this->assertEquals('Test instance 1', $instanceinfo1['name']);
        $this->assertTrue($instanceinfo1['status']);
        $this->assertFalse(isset($instanceinfo1['enrolpassword']));

        $instanceinfo2 = enrol_bank_external::get_instance_info($instanceid2);
        $instanceinfo2 = external_api::clean_returnvalue(enrol_bank_external::get_instance_info_returns(), $instanceinfo2);
        $this->assertEquals($instanceid2, $instanceinfo2['id']);
        $this->assertEquals($course->id, $instanceinfo2['courseid']);
        $this->assertEquals('bank', $instanceinfo2['type']);
        $this->assertEquals('Test instance 2', $instanceinfo2['name']);
        $this->assertEquals(get_string('canntenrol', 'enrol_bank'), $instanceinfo2['status']);
        $this->assertFalse(isset($instanceinfo2['enrolpassword']));

        $instanceinfo3 = enrol_bank_external::get_instance_info($instanceid3);
        $instanceinfo3 = external_api::clean_returnvalue(enrol_bank_external::get_instance_info_returns(), $instanceinfo3);
        $this->assertEquals($instanceid3, $instanceinfo3['id']);
        $this->assertEquals($course->id, $instanceinfo3['courseid']);
        $this->assertEquals('bank', $instanceinfo3['type']);
        $this->assertEquals('Test instance 3', $instanceinfo3['name']);
        $this->assertTrue($instanceinfo3['status']);
        $this->assertEquals(get_string('password', 'enrol_bank'), $instanceinfo3['enrolpassword']);

        // Try to retrieve information using a normal user for a hidden course.
        $user = bank::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            enrol_bank_external::get_instance_info($instanceid3);
        } catch (moodle_exception $e) {
            $this->assertEquals('coursehidden', $e->errorcode);
        }
    }

    /**
     * Test enrol_user
     */
    public function test_enrol_user() {
        global $DB;

        bank::resetAfterTest(true);

        $user = bank::getDataGenerator()->create_user();
        bank::setUser($user);

        $course1 = bank::getDataGenerator()->create_course();
        $course2 = bank::getDataGenerator()->create_course(array('groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1));
        $user1 = bank::getDataGenerator()->create_user();
        $user2 = bank::getDataGenerator()->create_user();
        $user3 = bank::getDataGenerator()->create_user();
        $user4 = bank::getDataGenerator()->create_user();

        $context1 = context_course::instance($course1->id);
        $context2 = context_course::instance($course2->id);

        $bankplugin = enrol_get_plugin('bank');
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $instance1id = $bankplugin->add_instance($course1, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'name' => 'Test instance 1',
                                                                'customint6' => 1,
                                                                'roleid' => $studentrole->id));
        $instance2id = $bankplugin->add_instance($course2, array('status' => ENROL_INSTANCE_DISABLED,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 2',
                                                                'roleid' => $studentrole->id));
        $instance1 = $DB->get_record('enrol', array('id' => $instance1id), '*', MUST_EXIST);
        $instance2 = $DB->get_record('enrol', array('id' => $instance2id), '*', MUST_EXIST);

        bank::setUser($user1);

        // bank enrol me.
        $result = enrol_bank_external::enrol_user($course1->id);
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);

        bank::assertTrue($result['status']);
        bank::assertEquals(1, $DB->count_records('user_enrolments', array('enrolid' => $instance1->id)));
        bank::assertTrue(is_enrolled($context1, $user1));

        // Add password.
        $instance2->password = 'abcdef';
        $DB->update_record('enrol', $instance2);

        // Try instance not enabled.
        try {
            enrol_bank_external::enrol_user($course2->id);
        } catch (moodle_exception $e) {
            bank::assertEquals('canntenrol', $e->errorcode);
        }

        // Enable the instance.
        $bankplugin->update_status($instance2, ENROL_INSTANCE_ENABLED);

        // Try not passing a key.
        $result = enrol_bank_external::enrol_user($course2->id);
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertFalse($result['status']);
        bank::assertCount(1, $result['warnings']);
        bank::assertEquals('4', $result['warnings'][0]['warningcode']);

        // Try passing an invalid key.
        $result = enrol_bank_external::enrol_user($course2->id, 'invalidkey');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertFalse($result['status']);
        bank::assertCount(1, $result['warnings']);
        bank::assertEquals('4', $result['warnings'][0]['warningcode']);

        // Try passing an invalid key with hint.
        $bankplugin->set_config('showhint', true);
        $result = enrol_bank_external::enrol_user($course2->id, 'invalidkey');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertFalse($result['status']);
        bank::assertCount(1, $result['warnings']);
        bank::assertEquals('3', $result['warnings'][0]['warningcode']);

        // Everything correct, now.
        $result = enrol_bank_external::enrol_user($course2->id, 'abcdef');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);

        bank::assertTrue($result['status']);
        bank::assertEquals(1, $DB->count_records('user_enrolments', array('enrolid' => $instance2->id)));
        bank::assertTrue(is_enrolled($context2, $user1));

        // Try group password now, other user.
        $instance2->customint1 = 1;
        $instance2->password = 'zyx';
        $DB->update_record('enrol', $instance2);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course2->id, 'enrolmentkey' => 'zyx'));

        bank::setUser($user2);
        // Try passing and invalid key for group.
        $result = enrol_bank_external::enrol_user($course2->id, 'invalidkey');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertFalse($result['status']);
        bank::assertCount(1, $result['warnings']);
        bank::assertEquals('2', $result['warnings'][0]['warningcode']);

        // Now, everything ok.
        $result = enrol_bank_external::enrol_user($course2->id, 'zyx');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);

        bank::assertTrue($result['status']);
        bank::assertEquals(2, $DB->count_records('user_enrolments', array('enrolid' => $instance2->id)));
        bank::assertTrue(is_enrolled($context2, $user2));

        // Try multiple instances now, multiple errors.
        $instance3id = $bankplugin->add_instance($course2, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 2',
                                                                'roleid' => $studentrole->id));
        $instance3 = $DB->get_record('enrol', array('id' => $instance3id), '*', MUST_EXIST);
        $instance3->password = 'abcdef';
        $DB->update_record('enrol', $instance3);

        bank::setUser($user3);
        $result = enrol_bank_external::enrol_user($course2->id, 'invalidkey');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertFalse($result['status']);
        bank::assertCount(2, $result['warnings']);

        // Now, everything ok.
        $result = enrol_bank_external::enrol_user($course2->id, 'zyx');
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertTrue($result['status']);
        bank::assertTrue(is_enrolled($context2, $user3));

        // Now test passing an instance id.
        bank::setUser($user4);
        $result = enrol_bank_external::enrol_user($course2->id, 'abcdef', $instance3id);
        $result = external_api::clean_returnvalue(enrol_bank_external::enrol_user_returns(), $result);
        bank::assertTrue($result['status']);
        bank::assertTrue(is_enrolled($context2, $user3));
        bank::assertCount(0, $result['warnings']);
        bank::assertEquals(1, $DB->count_records('user_enrolments', array('enrolid' => $instance3->id)));
    }
}
