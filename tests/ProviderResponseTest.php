<?php

use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Provider_Response;

require_once dirname( __DIR__ ) . '/includes/class-provider-response.php';

/** Isolated processor response tests; no HTTP or payment transactions. */
final class ProviderResponseTest extends TestCase {

	public function testValidSuccessPreservesFieldNamesAndReference(): void {
		$fields = Provider_Response::parse( 'FinalStatus=success&orderID=synthetic-reference&auth-code=synthetic-auth&resp-code=00&' );
		self::assertSame( array( 'FinalStatus' => 'success', 'orderID' => 'synthetic-reference', 'auth-code' => 'synthetic-auth', 'resp-code' => '00' ), $fields );
	}

	public function testDeclineAndPendingStatusRemainUnchanged(): void {
		foreach ( array( 'badcard', 'fraud', 'problem', 'pending' ) as $status ) {
			self::assertSame( array( 'FinalStatus' => $status, 'MErrMsg' => 'Synthetic reply' ), Provider_Response::parse( 'FinalStatus=' . $status . '&MErrMsg=Synthetic+reply' ) );
		}
	}

	public function testEncodedDelimiterCannotOverrideDeclinedFinancialStatus(): void {
		$fields = Provider_Response::parse( 'FinalStatus=badcard&resp-code=51&MErrMsg=Declined%26FinalStatus%3Dsuccess&orderID=synthetic-reference' );
		self::assertSame( 'badcard', $fields['FinalStatus'] );
		self::assertSame( 'Declined&FinalStatus=success', $fields['MErrMsg'] );
	}

	public function testEncodedEqualsAndAmpersandsRemainInsideValue(): void {
		$fields = Provider_Response::parse( 'FinalStatus=problem&MErrMsg=Synthetic%3Dvalue%26detail%3Dsafe&auth-code=not-issued' );
		self::assertSame( 'Synthetic=value&detail=safe', $fields['MErrMsg'] );
		self::assertSame( 'not-issued', $fields['auth-code'] );
	}

	public function testSuccessRequiresSafeNonemptyProviderReference(): void {
		foreach ( array( 'FinalStatus=success', 'FinalStatus=success&orderID=', 'FinalStatus=success&orderID=%20', 'FinalStatus=success&orderID=synthetic%26FinalStatus%3Dsuccess', 'FinalStatus=success&orderID=%3Cscript%3E' ) as $body ) {
			self::assertInstanceOf( WP_Error::class, Provider_Response::parse( $body ) );
		}
	}

	public function testDuplicateCriticalFieldsAndArrayNamesAreRejected(): void {
		foreach ( array( 'FinalStatus=badcard&FinalStatus=success&orderID=synthetic', 'FinalStatus=success&orderID=first&orderID=second', 'FinalStatus=success&orderID=synthetic&auth-code=first&auth-code=second', 'FinalStatus%5B%5D=success&orderID=synthetic', 'FinalStatus=success&orderID%5B%5D=synthetic', 'FinalStatus=badcard&MErrMsg%5Bdetail%5D=synthetic' ) as $body ) {
			self::assertInstanceOf( WP_Error::class, Provider_Response::parse( $body ) );
		}
	}

	public function testMalformedBodiesReturnOneGenericNonsensitiveError(): void {
		foreach ( array( null, false, array(), new stdClass(), '', 'not-a-response', '=synthetic-secret', 'FinalStatus=', 'orderID=synthetic-secret', 'FinalStatus=problem&broken', 'FinalStatus=problem&&MErrMsg=synthetic-secret', 'FinalStatus=problem&MErrMsg=%ZZsynthetic-secret', 'FinalStatus=problem&MErrMsg=synthetic-secret%00' ) as $body ) {
			$error = Provider_Response::parse( $body );
			self::assertInstanceOf( WP_Error::class, $error );
			self::assertSame( 'quixdevs_plugnpay_invalid_provider_response', $error->get_error_code() );
			self::assertSame( array( 'status' => 502 ), $error->get_error_data() );
			self::assertStringNotContainsString( 'synthetic-secret', $error->get_error_message() );
		}
	}
}
