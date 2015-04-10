<?php

class ElitEmailBitlyLink extends WP_UnitTestCase {

  private $post;
  private $token;

  public function setUp() {
    parent::setUp();
    $args = array(
      'post_title' => 'JAOA case report: OMT resolves infant’s obstructed tear duct',
      'post_excerpt' => 'Although more research is needed, OMT is a potential conservative first-line treatment for patients with persistent dacryostenosis.',
    );
    // our class hooks into transition_post_status;
    // let's turn off this action so we don't trigger any email-sending
    remove_all_actions( 'transition_post_status', 10 ); 
    $this->post = get_post( $this->factory->post->create( $args ) );
    $this->token = '12345';
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function testPostIsNewlyPublishedReturnsFalse() {
    $actual = elit_post_is_newly_published( 'draft', 'publish' );
    $this->assertFalse( $actual );
    $actual = elit_post_is_newly_published( 'publish', 'publish' );
    $this->assertFalse( $actual );
    $actual = elit_post_is_newly_published( 'auto-draft', 'publish' );
    $this->assertFalse( $actual );
    $actual = elit_post_is_newly_published( 'private', 'private' );
    $this->assertFalse( $actual );
  
    $actual = elit_post_is_newly_published( 'publish', '' );
    $this->assertFalse( $actual );
  
    $actual = elit_post_is_newly_published( 'auto-draft', 'auto-draft' );
    $this->assertFalse( $actual );
    $actual = elit_post_is_newly_published( 'trash', 'publish' );
    $this->assertFalse( $actual );
    $actual = elit_post_is_newly_published( 'inherit', 'publish' );
    $this->assertFalse( $actual );
  }

  public function testPostIsNewlyPublishedReturnsTrue() {
    $actual = elit_post_is_newly_published( 'publish', 'draft' );
    $this->assertTrue( $actual );
    $actual = elit_post_is_newly_published( 'publish', 'private' );
    $this->assertTrue( $actual );
    $actual = elit_post_is_newly_published( 'publish', 'auto-draft' );
    $this->assertTrue( $actual );

    $actual = elit_post_is_newly_published( 'publish', 'pending' );
    $this->assertTrue( $actual );

    $actual = elit_post_is_newly_published( 'publish', 'future' );
    $this->assertTrue( $actual );
    $actual = elit_post_is_newly_published( 'publish', 'inherit' );
    $this->assertTrue( $actual );
    $actual = elit_post_is_newly_published( 'publish', 'trash' );
    $this->assertTrue( $actual );
  }

  public function testElitQueryStringForLinkSave() {
    $exp = 'access_token=12345&longUrl=http%3A%2F%2Fexample.org&title=JAOA+case+report%3A+OMT+resolves+infant%E2%80%99s+obstructed+tear+duct';
    $actual = elit_query_string_for_link_save( $this->post->post_title, $this->token );
    $this->assertEquals( $exp, $actual );
  }

  public function testElitUrlForBitlyRequest() {
    $exp = 'https://api-ssl.bitly.com/v3/user/link_save?access_token=12345&longUrl=http%3A%2F%2Fexample.org&title=JAOA+case+report%3A+OMT+resolves+infant%E2%80%99s+obstructed+tear+duct';
    $actual = elit_url_for_bitly_link_save_request( $this->post->post_title, $this->token );
    $this->assertEquals( $exp, $actual );
  }

  public function testGetBitlyLinkFromResponse() {
    $json = '{"status_code": 304, "data": {"link_save": {"link": "http://bit.ly/1HVQ4Mp", "aggregate_link": "http://bit.ly/2jGeKo", "long_url": "http://example.org", "new_link": 0}}, "status_txt": "LINK_ALREADY_EXISTS"}';
    
    // this is the form of a return from wp_remote_get()
    $response = array(
        'headers' => array(),
        'body' => $json,
        'response' =>array(),
        'cookies' => array(),
    );

    $exp = 'http://bit.ly/1HVQ4Mp';
    $actual = elit_get_bitly_link_from_response( $response );
    $this->assertEquals( $exp, $actual );
  }

  public function testElitGetKicker() {
    add_post_meta( $this->post->ID, 'elit_kicker', 'hello there' );
    $this->assertEquals( 'hello there', get_post_meta( $this->post->ID, 'elit_kicker', true ) );

    $exp = 'HELLO THERE';
    $actual = elit_get_kicker( $this->post->ID );

    $this->assertEquals( $exp, $actual );
  }


  public function testElitGetEmailMessage() {
    $this->markTestIncomplete();
    add_post_meta( $this->post->ID, 'elit_kicker', 'hello there' );
    
    $link = 'http://bit.ly/1HVQ4Mp';
    
    $exp = 'A new post has been published: ' . PHP_EOL . PHP_EOL;
    $exp .= 'HELLO THERE' . PHP_EOL;
    $exp .= $this->post->post_title . PHP_EOL;
    $exp .= $this->post->post_excerpt  . PHP_EOL;
    $exp .= get_the_permalink( $this->post->ID ). PHP_EOL;
    $exp .= 'Bitly: ' . $link . PHP_EOL . PHP_EOL;

    $actual = elit_get_email_message( $link, $this->post );
    print_r( PHP_EOL. $actual );

  }

  
}

